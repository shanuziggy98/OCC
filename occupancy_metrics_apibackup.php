<?php
// API for calculating detailed occupancy metrics like the Google Sheet example
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

// Database configuration
$host = "mysql327.phy.lolipop.lan";
$db_name = "LAA0963548-occ";
$username = "LAA0963548";
$password = "EXseed55";

class OccupancyMetricsAPI {
    private $pdo;

    public function __construct() {
        try {
            $this->pdo = new PDO("mysql:host=" . $GLOBALS['host'] . ";dbname=" . $GLOBALS['db_name'],
                               $GLOBALS['username'], $GLOBALS['password']);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->exec("set names utf8");
        } catch(PDOException $e) {
            throw new Exception("Database connection failed: " . $e->getMessage());
        }
    }

    private function sanitizeTableName($propertyName) {
        $tableName = strtolower($propertyName);
        $tableName = preg_replace('/[^a-z0-9_]/', '_', $tableName);
        $tableName = preg_replace('/_+/', '_', $tableName);
        $tableName = trim($tableName, '_');
        return $tableName;
    }

    private function getDaysInMonth($year, $month) {
        return cal_days_in_month(CAL_GREGORIAN, $month, $year);
    }

    private function isHostelProperty($propertyName) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT property_type
                FROM property_sheets
                WHERE property_name = ?
                LIMIT 1
            ");
            $stmt->execute([$propertyName]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result && $result['property_type'] === 'hostel';
        } catch (Exception $e) {
            error_log("Error checking property type for {$propertyName}: " . $e->getMessage());
            // Fallback to hardcoded list for safety
            return in_array(strtolower($propertyName), ['iwatoyama', 'goettingen', 'littlehouse']);
        }
    }

    private function getPropertyMetrics($propertyName, $year, $month, $roomFilter = null) {
        $tableName = $this->sanitizeTableName($propertyName);
        $daysInMonth = $this->getDaysInMonth($year, $month);

        // Check if this is Kaguya - it uses a special monthly commission table
        $isKaguyaWithMonthlyData = false;
        $kaguyaMonthlyData = null;
        if (strtolower($propertyName) === 'kaguya') {
            $kaguyaMonthlyData = $this->getKaguyaMonthlyCommission($year, $month);
            $isKaguyaWithMonthlyData = ($kaguyaMonthlyData !== null);
        }

        // Check if table exists
        $stmt = $this->pdo->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$tableName]);
        if ($stmt->rowCount() == 0) {
            return null; // Table doesn't exist
        }

        try {
            // Get all bookings that overlap with the target month
            $firstDayOfMonth = "{$year}-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-01";
            $lastDayOfMonth = date('Y-m-t', strtotime($firstDayOfMonth));

            $whereClause = "WHERE accommodation_fee > 0 AND check_in <= ? AND check_out > ?";
            $params = [$lastDayOfMonth, $firstDayOfMonth];

            // Use dynamic property type checking instead of hardcoded names
            if ($roomFilter && $this->isHostelProperty($propertyName)) {
                $whereClause .= " AND room_type = ?";
                $params[] = $roomFilter;
            }

            $stmt = $this->pdo->prepare("
                SELECT
                    check_in,
                    check_out,
                    night_count,
                    accommodation_fee,
                    booking_date,
                    room_type,
                    COALESCE(people_count, 0) as people_count
                FROM `{$tableName}`
                {$whereClause}
            ");

            $stmt->execute($params);
            $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Calculate nights that fall within this specific month
            $booked_nights = 0;
            $booking_count = 0;
            $room_revenue = 0;
            $total_lead_time = 0;
            $lead_time_count = 0;
            $total_people = 0;
            $total_stay_cleanings = 0;
            $checkout_cleaning_dates = []; // Track check-in dates for checkout cleaning
            $stay_cleaning_dates = []; // Track dates when stay cleaning occurs

            foreach ($bookings as $booking) {
                $checkIn = new DateTime($booking['check_in']);
                $checkOut = new DateTime($booking['check_out']);
                $monthStart = new DateTime($firstDayOfMonth);
                $monthEnd = new DateTime($lastDayOfMonth);
                $monthEnd->setTime(23, 59, 59);

                // Determine the actual start and end dates within the month
                $effectiveStart = max($checkIn, $monthStart);
                $effectiveEnd = min($checkOut, $monthEnd->modify('+1 day'));

                // Calculate nights in this month
                $nightsInMonth = $effectiveStart->diff($effectiveEnd)->days;

                if ($nightsInMonth > 0) {
                    $booked_nights += $nightsInMonth;

                    // Calculate proportional revenue for this month
                    $totalNights = intval($booking['night_count']);
                    if ($totalNights > 0) {
                        $proportionalRevenue = ($nightsInMonth / $totalNights) * floatval($booking['accommodation_fee']);
                        $room_revenue += $proportionalRevenue;
                    }

                    // Count booking if check-in is in this month
                    if ($checkIn >= $monthStart && $checkIn <= $monthEnd) {
                        $booking_count++;

                        // Track check-in date for checkout cleaning (format: M/D)
                        $checkout_cleaning_dates[] = intval($checkIn->format('n')) . '/' . intval($checkIn->format('j'));

                        // Track people count for fixed commission calculation
                        $total_people += intval($booking['people_count']);

                        // Calculate stay cleanings (for every 3 days, but NOT on checkout day)
                        $nightCount = intval($booking['night_count']);
                        if ($nightCount > 3) { // Changed from >= 3 to > 3
                            // Stay cleaning happens every 3 days DURING the stay
                            // But NOT on the checkout day (checkout day gets regular checkout cleaning)
                            // Formula: floor((night_count - 1) / 3)
                            // Examples:
                            //   3 nights (days 1,2,3) -> floor(2/3) = 0 (no stay cleaning, just checkout cleaning on day 3)
                            //   4 nights (days 1,2,3,4) -> floor(3/3) = 1 (stay cleaning on day 3, checkout on day 4)
                            //   6 nights (days 1,2,3,4,5,6) -> floor(5/3) = 1 (stay cleaning on day 3, checkout on day 6)
                            //   7 nights (days 1,2,3,4,5,6,7) -> floor(6/3) = 2 (stay cleaning on day 3 and 6, checkout on day 7)
                            $stayCleaningCount = floor(($nightCount - 1) / 3);
                            $total_stay_cleanings += $stayCleaningCount;

                            // Track dates when stay cleaning occurs (every 3 days from check-in, but not on checkout)
                            for ($i = 1; $i <= $stayCleaningCount; $i++) {
                                $cleaningDate = clone $checkIn;
                                $cleaningDate->modify('+' . ($i * 3) . ' days');
                                $stay_cleaning_dates[] = intval($cleaningDate->format('n')) . '/' . intval($cleaningDate->format('j'));
                            }
                        }

                        // Calculate lead time
                        if (!empty($booking['booking_date'])) {
                            $bookingDate = new DateTime($booking['booking_date']);
                            $leadTime = $bookingDate->diff($checkIn)->days;
                            $total_lead_time += $leadTime;
                            $lead_time_count++;
                        }
                    }
                }
            }

            $avg_lead_time = $lead_time_count > 0 ? round($total_lead_time / $lead_time_count, 1) : 0;

            // Default room count (you can make this configurable per property)
            $default_rooms = $this->getDefaultRoomCount($propertyName, $roomFilter);

            $available_rooms = $daysInMonth * $default_rooms; // Total room-nights available
            $sold_rooms = $booked_nights; // Same as booked nights

            // Calculate rates
            $occ_rate = $available_rooms > 0 ? ($booked_nights / $available_rooms) * 100 : 0;
            $adr = $booked_nights > 0 ? $room_revenue / $booked_nights : 0; // Average Daily Rate
            $revpar = $available_rooms > 0 ? $room_revenue / $available_rooms : 0; // Revenue Per Available Room

            // For Kaguya with monthly data, use that data directly
            if ($isKaguyaWithMonthlyData) {
                // Use the monthly commission data from the spreadsheet
                $ota_commission = floatval($kaguyaMonthlyData['exseed_commission']);
                $agency_fee = $ota_commission;
                $commission_percent = floatval($kaguyaMonthlyData['commission_percentage']);
                $owner_payment = floatval($kaguyaMonthlyData['owner_payment']);
                $isFixedCommission = false; // It's a custom monthly calculation
            } else {
                // Check if this property uses fixed commission
                $fixedSettings = $this->getFixedCommissionSettings($propertyName);
                $isFixedCommission = $fixedSettings && $fixedSettings['commission_method'] === 'fixed';

                // Calculate commission
                $ota_commission = $this->calculateCommission($room_revenue, $propertyName, $roomFilter, $booking_count, $total_people, $total_stay_cleanings);
                $agency_fee = $this->getAgencyFee($propertyName, $room_revenue, $roomFilter, $booking_count, $total_people, $total_stay_cleanings);
            }

            // Calculate cleaning fees based on commission type
            if ($isKaguyaWithMonthlyData) {
                // For Kaguya with monthly data: No per-booking cleaning fee breakdown
                $cleaning_fee_per_time = 0;
                $total_cleaning_fee = 0;
                // commission_percent already set above
            } elseif ($isFixedCommission) {
                // For fixed commission: Total cleaning includes checkout + stay cleanings
                $checkout_cleaning_total = $booking_count * floatval($fixedSettings['checkout_cleaning_fee']);
                $stay_cleaning_total = $total_stay_cleanings * floatval($fixedSettings['stay_cleaning_fee']);
                $total_cleaning_fee = $checkout_cleaning_total + $stay_cleaning_total;

                // Cleaning fee per time = total / booking count
                $cleaning_fee_per_time = $booking_count > 0 ? $total_cleaning_fee / $booking_count : 0;

                // Calculate commission as percentage of revenue for display
                $commission_percent = $room_revenue > 0 ? ($ota_commission / $room_revenue) * 100 : 0;
            } else {
                // For percentage commission: Use traditional calculation
                $cleaning_fee_per_time = $this->getCleaningFeePerTime($propertyName, $roomFilter);
                $total_cleaning_fee = $booking_count * $cleaning_fee_per_time;
                $commission_percent = $this->getCommissionPercent($propertyName, $roomFilter);
            }

            $result = [
                'property_name' => $propertyName,
                'booked_nights' => $booked_nights,
                'booking_count' => $booking_count,
                'available_rooms' => $available_rooms,
                'sold_rooms' => $sold_rooms,
                'room_revenue' => $room_revenue,
                'occ_rate' => $occ_rate,
                'adr' => $adr,
                'revpar' => $revpar,
                'cleaning_fee_per_time' => $cleaning_fee_per_time,
                'total_cleaning_fee' => $total_cleaning_fee,
                'ota_commission' => $ota_commission,
                'commission_percent' => $commission_percent,
                'agency_fee' => $agency_fee,
                'avg_lead_time' => $avg_lead_time,
                'total_people' => $total_people,
                'total_stay_cleanings' => $total_stay_cleanings
            ];

            // Add Kaguya monthly commission breakdown if using monthly data
            if ($isKaguyaWithMonthlyData) {
                $result['commission_method'] = 'kaguya_monthly';
                $result['commission_breakdown'] = [
                    'total_sales' => floatval($kaguyaMonthlyData['total_sales']),
                    'owner_payment' => $owner_payment,
                    'exseed_commission' => $ota_commission,
                    'commission_percentage' => $commission_percent,
                    'year' => intval($kaguyaMonthlyData['year']),
                    'month' => intval($kaguyaMonthlyData['month']),
                    'notes' => $kaguyaMonthlyData['notes'] ?? '',
                    'data_source' => 'kaguya_monthly_commission_table'
                ];
            } elseif ($isFixedCommission) {
                // Add fixed commission breakdown if using fixed method
                $result['commission_method'] = 'fixed';

                // Detailed breakdown with all cleaning components
                $checkout_cleaning_fee_each = floatval($fixedSettings['checkout_cleaning_fee']);
                $stay_cleaning_fee_each = floatval($fixedSettings['stay_cleaning_fee']);
                $linen_fee_each = floatval($fixedSettings['linen_fee_per_person']);

                $total_checkout = $booking_count * $checkout_cleaning_fee_each;
                $total_stay = $total_stay_cleanings * $stay_cleaning_fee_each;
                $total_linen = $total_people * $linen_fee_each;

                $result['commission_breakdown'] = [
                    // Checkout cleaning
                    'checkout_cleaning_fee_per_booking' => $checkout_cleaning_fee_each,
                    'checkout_cleaning_count' => $booking_count,
                    'total_checkout_cleaning' => $total_checkout,
                    'checkout_cleaning_dates' => $checkout_cleaning_dates,

                    // Stay cleaning
                    'stay_cleaning_fee_per_cleaning' => $stay_cleaning_fee_each,
                    'stay_cleaning_count' => $total_stay_cleanings,
                    'total_stay_cleaning' => $total_stay,
                    'stay_cleaning_dates' => $stay_cleaning_dates,

                    // Total cleaning (checkout + stay)
                    'total_all_cleaning' => $total_checkout + $total_stay,
                    'average_cleaning_per_booking' => $booking_count > 0 ? ($total_checkout + $total_stay) / $booking_count : 0,

                    // Linen
                    'linen_fee_per_person' => $linen_fee_each,
                    'total_people' => $total_people,
                    'total_linen_fee' => $total_linen,

                    // Monthly fees (reference only - not included in booking total)
                    'operation_management_fee_monthly' => floatval($fixedSettings['operation_management_fee']),
                    'monthly_inspection_fee' => floatval($fixedSettings['monthly_inspection_fee']),
                    'emergency_staff_fee_monthly' => isset($fixedSettings['emergency_staff_fee']) ? floatval($fixedSettings['emergency_staff_fee']) : 0,
                    'garbage_collection_fee_monthly' => isset($fixedSettings['garbage_collection_fee']) ? floatval($fixedSettings['garbage_collection_fee']) : 0,

                    // Total variable fees (per booking calculation)
                    'total_variable_fees' => $total_checkout + $total_stay + $total_linen
                ];
            } else {
                $result['commission_method'] = 'percentage';
            }

            // Add room information if filtering
            if ($roomFilter) {
                $result['room_type'] = $roomFilter;
            }

            return $result;

        } catch (Exception $e) {
            error_log("Error calculating metrics for {$propertyName}: " . $e->getMessage());
            return null;
        }
    }

    private function getDefaultRoomCount($propertyName, $roomFilter = null) {
        // IMPORTANT: When filtering by specific room, return 1 (individual room)
        // When NOT filtering (whole property), return total room count

        // If we're filtering by a specific room, it's always 1 room
        if ($roomFilter !== null) {
            return 1;
        }

        // Try to get room count from property_sheets room_list
        try {
            $stmt = $this->pdo->prepare("
                SELECT property_type, room_list
                FROM property_sheets
                WHERE property_name = ?
                LIMIT 1
            ");
            $stmt->execute([$propertyName]);
            $propertyInfo = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($propertyInfo) {
                // If it's a hostel with room_list, count ALL the rooms (not just 1)
                if ($propertyInfo['property_type'] === 'hostel' && !empty($propertyInfo['room_list'])) {
                    $rooms = explode(',', $propertyInfo['room_list']);
                    return count($rooms); // Total rooms in the property
                }
                // If it's a guesthouse, return 1
                if ($propertyInfo['property_type'] === 'guesthouse') {
                    return 1;
                }
            }
        } catch (Exception $e) {
            error_log("Error getting room count for {$propertyName}: " . $e->getMessage());
        }

        // Fallback room counts per property (for backward compatibility)
        $room_counts = [
            'comodita' => 1,
            'mujurin' => 1,
            'fujinomori' => 1,
            'enraku' => 1,
            'tsubaki' => 1,
            'hiiragi' => 1,
            'fushimi_apt' => 1,
            'kanon' => 1,
            'fushimi_house' => 1,
            'kado' => 1,
            'tanuki' => 1,
            'fukuro' => 1,
            'hauwa_apt' => 1,
            'littlehouse' => 3, // 3 rooms
            'yanagawa' => 1,
            'nishijin_fujita' => 1,
            'rikyu' => 1,
            'hiroshima' => 1,
            'okinawa' => 1,
            'iwatoyama' => 38, // Many rooms as shown in your data
            'goettingen' => 11, // Fixed: was 10, should be 11
            'kaguya' => 3, // 3 rooms: 風の間, 鳥の間, 花の間
            'ryoma' => 1,
            'isa' => 1,
            'yura' => 1,
            'konoha' => 1
        ];

        $tableName = $this->sanitizeTableName($propertyName);
        return $room_counts[$tableName] ?? 1;
    }

    private function getFixedCommissionSettings($propertyName) {
        // Get fixed commission settings from property_commission_settings table
        try {
            $stmt = $this->pdo->prepare("
                SELECT *
                FROM property_commission_settings
                WHERE property_name = ? AND is_active = TRUE
                LIMIT 1
            ");
            $stmt->execute([$propertyName]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result) {
                return $result;
            }
        } catch (Exception $e) {
            error_log("Error getting fixed commission settings for {$propertyName}: " . $e->getMessage());
        }

        return null;
    }

    private function getKaguyaMonthlyCommission($year, $month) {
        // Get Kaguya's monthly commission data from custom table
        try {
            $stmt = $this->pdo->prepare("
                SELECT *
                FROM kaguya_monthly_commission
                WHERE year = ? AND month = ?
                LIMIT 1
            ");
            $stmt->execute([$year, $month]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result) {
                return $result;
            }
        } catch (Exception $e) {
            error_log("Error getting Kaguya monthly commission for {$year}-{$month}: " . $e->getMessage());
        }

        return null;
    }

    private function getPropertySettings($propertyName, $roomFilter = null) {
        // Get settings from property_settings table
        try {
            // Try to get room-specific settings first if room filter is provided
            if ($roomFilter !== null) {
                $stmt = $this->pdo->prepare("
                    SELECT commission_percent, commission_calculation_method, cleaning_fee
                    FROM property_settings
                    WHERE property_name = ? AND room_name = ? AND is_active = TRUE
                    LIMIT 1
                ");
                $stmt->execute([$propertyName, $roomFilter]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($result) {
                    return $result;
                }
            }

            // If no room-specific setting found, get property-level settings
            $stmt = $this->pdo->prepare("
                SELECT commission_percent, commission_calculation_method, cleaning_fee
                FROM property_settings
                WHERE property_name = ? AND room_name IS NULL AND is_active = TRUE
                LIMIT 1
            ");
            $stmt->execute([$propertyName]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result) {
                return $result;
            }
        } catch (Exception $e) {
            error_log("Error getting property settings for {$propertyName}: " . $e->getMessage());
        }

        // Fallback to default settings
        return [
            'commission_percent' => 15.00,
            'commission_calculation_method' => 'regular',
            'cleaning_fee' => 5000.00
        ];
    }

    private function getCleaningFeePerTime($propertyName, $roomFilter = null) {
        $settings = $this->getPropertySettings($propertyName, $roomFilter);
        return floatval($settings['cleaning_fee']);
    }

    private function getCommissionPercent($propertyName, $roomFilter = null) {
        $settings = $this->getPropertySettings($propertyName, $roomFilter);
        return floatval($settings['commission_percent']);
    }

    private function calculateCommission($revenue, $propertyName, $roomFilter = null, $bookingCount = 0, $totalPeople = 0, $totalStayCleanings = 0) {
        // First check if this property uses fixed commission
        $fixedSettings = $this->getFixedCommissionSettings($propertyName);

        if ($fixedSettings && $fixedSettings['commission_method'] === 'fixed') {
            // Fixed commission calculation
            $checkoutCleaningFee = floatval($fixedSettings['checkout_cleaning_fee']);
            $stayCleaningFee = floatval($fixedSettings['stay_cleaning_fee']);
            $linenFeePerPerson = floatval($fixedSettings['linen_fee_per_person']);

            $totalCommission =
                ($bookingCount * $checkoutCleaningFee) +           // Check-out cleaning per booking
                ($totalStayCleanings * $stayCleaningFee) +         // Stay cleanings
                ($totalPeople * $linenFeePerPerson);               // Linen fees per person

            return $totalCommission;
        }

        // Otherwise use percentage-based calculation
        $settings = $this->getPropertySettings($propertyName, $roomFilter);
        $commissionPercent = floatval($settings['commission_percent']);
        $calculationMethod = $settings['commission_calculation_method'];

        switch ($calculationMethod) {
            case 'regular':
                // Standard percentage calculation
                return $revenue * ($commissionPercent / 100);

            case 'com_option1':
                // Placeholder for option1 calculation logic (to be defined later)
                // For now, use regular calculation
                return $revenue * ($commissionPercent / 100);

            case 'com_option2':
                // Placeholder for option2 calculation logic (to be defined later)
                // For now, use regular calculation
                return $revenue * ($commissionPercent / 100);

            default:
                // Default to regular calculation
                return $revenue * ($commissionPercent / 100);
        }
    }

    private function getAgencyFee($propertyName, $revenue, $roomFilter = null, $bookingCount = 0, $totalPeople = 0, $totalStayCleanings = 0) {
        // Use the same commission calculation logic
        return $this->calculateCommission($revenue, $propertyName, $roomFilter, $bookingCount, $totalPeople, $totalStayCleanings);
    }

    private function getPropertyRooms($propertyName, $year, $month) {
        // First, check if this is a hostel and get room_list from property_sheets
        try {
            $stmt = $this->pdo->prepare("
                SELECT property_type, room_list
                FROM property_sheets
                WHERE property_name = ?
                LIMIT 1
            ");
            $stmt->execute([$propertyName]);
            $propertyInfo = $stmt->fetch(PDO::FETCH_ASSOC);

            // If it's a hostel with room_list defined, use that
            if ($propertyInfo && $propertyInfo['property_type'] === 'hostel' && !empty($propertyInfo['room_list'])) {
                $rooms = array_map('trim', explode(',', $propertyInfo['room_list']));
                return $rooms;
            }
        } catch (Exception $e) {
            error_log("Error getting property info for {$propertyName}: " . $e->getMessage());
        }

        // Fallback: Get distinct room types from property table that have bookings
        $tableName = $this->sanitizeTableName($propertyName);

        try {
            $stmt = $this->pdo->prepare("
                SELECT DISTINCT room_type
                FROM `{$tableName}`
                WHERE YEAR(check_in) = ? AND MONTH(check_in) = ?
                AND room_type IS NOT NULL
                AND room_type != ''
                ORDER BY room_type
            ");
            $stmt->execute([$year, $month]);
            $rooms = $stmt->fetchAll(PDO::FETCH_COLUMN);

            // If no rooms found for the specific month, return the predefined list
            if (empty($rooms)) {
                return $this->getPredefinedRooms($propertyName);
            }

            return $rooms;
        } catch (Exception $e) {
            error_log("Error getting {$propertyName} rooms: " . $e->getMessage());
            // Return predefined list as fallback
            return $this->getPredefinedRooms($propertyName);
        }
    }

    private function getPredefinedRooms($propertyName) {
        $predefinedRooms = [
            'iwatoyama' => [
                '岩戸山全体', 'ファミリー401', '共用D402A', '共用D402B', '共用D402C',
                '共用D402D', '共用D402E', '共用D402F', 'ダブル403', 'ダブル404',
                'ダブル405', 'ユニーク406', 'ユニーク407', 'ツイン408', 'ファミリー301',
                '女子D302A', '女子D302B', '女子D302C', '女子D302D', '女子D302E',
                '女子D302F', 'ダブル303', 'ダブル304', 'ダブル305', 'ユニーク306',
                'ユニーク307', 'ツイン308'
            ],
            'goettingen' => [
                '月沈原101', '月沈原102', '月沈原201', '月沈原202', '月沈原203',
                '月沈原204', '月沈原205', '月沈原301', '月沈原302', '月沈原303', '月沈原304'
            ],
            'littlehouse' => [
                'いぬねこ1F', 'いぬねこ2F', '秘密の部屋'
            ],
            'kaguya' => [
                '風の間', '鳥の間', '花の間'
            ]
        ];

        return $predefinedRooms[$propertyName] ?? [];
    }

    public function getAllMetrics($year, $month, $roomFilter = null) {
        // If room filter is specified, only get metrics for iwatoyama with that room
        if ($roomFilter) {
            $metrics = $this->getPropertyMetrics('iwatoyama', $year, $month, $roomFilter);
            return $metrics ? [$metrics] : [];
        }

        // Get all properties
        $stmt = $this->pdo->query("SELECT property_name FROM property_sheets WHERE is_active = TRUE ORDER BY display_order, property_name");
        $properties = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $results = [];
        foreach ($properties as $propertyName) {
            $metrics = $this->getPropertyMetrics($propertyName, $year, $month);
            if ($metrics !== null) {
                $results[] = $metrics;
            }
        }

        return $results;
    }

    public function getPropertyRoomMetrics($propertyName, $year, $month) {
        // Get metrics for each room separately for the specified property
        $propertyRooms = $this->getPropertyRooms($propertyName, $year, $month);
        $results = [];

        foreach ($propertyRooms as $room) {
            $metrics = $this->getPropertyMetrics($propertyName, $year, $month, $room);
            if ($metrics !== null) {
                $results[] = $metrics;
            }
        }

        return $results;
    }

    public function getDailyOccupancyRates($startDate, $endDate) {
        // Generate list of dates in the range
        $dates = [];
        $currentDate = new DateTime($startDate);
        $end = new DateTime($endDate);

        while ($currentDate <= $end) {
            $dates[] = $currentDate->format('Y-m-d');
            $currentDate->modify('+1 day');
        }

        // Get data from daily_occupancy_records table
        $stmt = $this->pdo->prepare("
            SELECT
                property_name,
                record_date,
                occupancy_rate,
                occupied_rooms,
                total_rooms,
                booking_count,
                room_revenue
            FROM daily_occupancy_records
            WHERE record_date BETWEEN ? AND ?
            ORDER BY property_name, record_date
        ");
        $stmt->execute([$startDate, $endDate]);
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Group by property
        $propertyData = [];
        foreach ($records as $record) {
            $propertyName = $record['property_name'];

            if (!isset($propertyData[$propertyName])) {
                $propertyData[$propertyName] = [
                    'property_name' => $propertyName,
                    'total_rooms' => intval($record['total_rooms']),
                    'daily_occupancy' => []
                ];
            }

            $propertyData[$propertyName]['daily_occupancy'][$record['record_date']] = floatval($record['occupancy_rate']);
        }

        // Fill in missing dates with 0
        foreach ($propertyData as &$property) {
            foreach ($dates as $date) {
                if (!isset($property['daily_occupancy'][$date])) {
                    $property['daily_occupancy'][$date] = 0.0;
                }
            }
            // Sort by date
            ksort($property['daily_occupancy']);
        }

        // If no records found, get properties from property_sheets
        if (empty($propertyData)) {
            $stmt = $this->pdo->query("
                SELECT property_name, total_rooms
                FROM property_sheets
                WHERE is_active = TRUE
                ORDER BY display_order, property_name
            ");
            $properties = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($properties as $property) {
                $dailyOccupancy = [];
                foreach ($dates as $date) {
                    $dailyOccupancy[$date] = 0.0;
                }

                $propertyData[$property['property_name']] = [
                    'property_name' => $property['property_name'],
                    'total_rooms' => intval($property['total_rooms']),
                    'daily_occupancy' => $dailyOccupancy
                ];
            }
        }

        return [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'dates' => $dates,
            'properties' => array_values($propertyData),
            'data_source' => empty($records) ? 'no_data' : 'database'
        ];
    }

    public function get180DayLimitData() {
        // Get all properties with 180-day limit
        $stmt = $this->pdo->prepare("
            SELECT property_name, total_rooms
            FROM property_sheets
            WHERE has_180_day_limit = TRUE AND is_active = TRUE
            ORDER BY display_order, property_name
        ");
        $stmt->execute();
        $properties = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($properties)) {
            return [
                'current_fiscal_year' => $this->getCurrentFiscalYear(),
                'fiscal_year_start' => $this->getFiscalYearStart(),
                'fiscal_year_end' => $this->getFiscalYearEnd(),
                'properties' => [],
                'message' => 'No properties with 180-day limit found'
            ];
        }

        $fiscalYearStart = $this->getFiscalYearStart();
        $fiscalYearEnd = $this->getFiscalYearEnd();
        $results = [];

        foreach ($properties as $property) {
            $propertyName = $property['property_name'];
            $tableName = $this->sanitizeTableName($propertyName);

            // Check if table exists
            $stmt = $this->pdo->prepare("SHOW TABLES LIKE ?");
            $stmt->execute([$tableName]);
            if ($stmt->rowCount() == 0) {
                continue;
            }

            try {
                // Calculate total booked days in the current fiscal year
                $stmt = $this->pdo->prepare("
                    SELECT
                        check_in,
                        check_out,
                        night_count
                    FROM `{$tableName}`
                    WHERE accommodation_fee > 0
                    AND check_in <= ?
                    AND check_out > ?
                ");
                $stmt->execute([$fiscalYearEnd, $fiscalYearStart]);
                $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $totalBookedDays = 0;
                $bookingDetails = [];

                foreach ($bookings as $booking) {
                    $checkIn = new DateTime($booking['check_in']);
                    $checkOut = new DateTime($booking['check_out']);
                    $fyStart = new DateTime($fiscalYearStart);
                    $fyEnd = new DateTime($fiscalYearEnd);
                    $fyEnd->setTime(23, 59, 59);

                    // Calculate nights that fall within the fiscal year
                    $effectiveStart = max($checkIn, $fyStart);
                    $effectiveEnd = min($checkOut, $fyEnd->modify('+1 day'));

                    $nightsInFiscalYear = $effectiveStart->diff($effectiveEnd)->days;

                    if ($nightsInFiscalYear > 0) {
                        $totalBookedDays += $nightsInFiscalYear;
                        $bookingDetails[] = [
                            'check_in' => $booking['check_in'],
                            'check_out' => $booking['check_out'],
                            'nights_in_fiscal_year' => $nightsInFiscalYear
                        ];
                    }
                }

                $remainingDays = 180 - $totalBookedDays;
                $utilizationPercent = ($totalBookedDays / 180) * 100;

                $results[] = [
                    'property_name' => $propertyName,
                    'total_rooms' => intval($property['total_rooms']),
                    'limit_days' => 180,
                    'booked_days' => $totalBookedDays,
                    'remaining_days' => max(0, $remainingDays),
                    'utilization_percent' => min(100, $utilizationPercent),
                    'is_over_limit' => $totalBookedDays > 180,
                    'booking_count' => count($bookingDetails),
                    'status' => $remainingDays > 60 ? 'safe' : ($remainingDays > 30 ? 'warning' : 'critical')
                ];

            } catch (Exception $e) {
                error_log("Error calculating 180-day limit for {$propertyName}: " . $e->getMessage());
            }
        }

        return [
            'current_fiscal_year' => $this->getCurrentFiscalYear(),
            'fiscal_year_start' => $fiscalYearStart,
            'fiscal_year_end' => $fiscalYearEnd,
            'properties' => $results
        ];
    }

    private function getCurrentFiscalYear() {
        // Japanese fiscal year runs from April 1 to March 31
        $today = new DateTime();
        $currentYear = intval($today->format('Y'));
        $currentMonth = intval($today->format('n'));

        // If we're in January, February, or March, we're in the fiscal year that started last year
        if ($currentMonth < 4) {
            return ($currentYear - 1) . '-' . $currentYear;
        } else {
            return $currentYear . '-' . ($currentYear + 1);
        }
    }

    private function getFiscalYearStart() {
        // Japanese fiscal year starts on April 1
        $today = new DateTime();
        $currentYear = intval($today->format('Y'));
        $currentMonth = intval($today->format('n'));

        // If we're in January, February, or March, the fiscal year started last year
        if ($currentMonth < 4) {
            return ($currentYear - 1) . '-04-01';
        } else {
            return $currentYear . '-04-01';
        }
    }

    private function getFiscalYearEnd() {
        // Japanese fiscal year ends on March 31
        $today = new DateTime();
        $currentYear = intval($today->format('Y'));
        $currentMonth = intval($today->format('n'));

        // If we're in January, February, or March, the fiscal year ends this year
        if ($currentMonth < 4) {
            return $currentYear . '-03-31';
        } else {
            return ($currentYear + 1) . '-03-31';
        }
    }

    public function route() {
        $action = $_GET['action'] ?? 'metrics';
        $year = intval($_GET['year'] ?? date('Y'));
        $month = intval($_GET['month'] ?? date('n'));
        $room = $_GET['room'] ?? null;

        try {
            switch ($action) {
                case 'metrics':
                    $metrics = $this->getAllMetrics($year, $month, $room);

                    // Calculate summary
                    $totalBooked = array_sum(array_column($metrics, 'booked_nights'));
                    $totalAvailable = array_sum(array_column($metrics, 'available_rooms'));
                    $overallOccRate = $totalAvailable > 0 ? ($totalBooked / $totalAvailable) * 100 : 0;

                    $response = [
                        'year' => $year,
                        'month' => $month,
                        'overall_occupancy_rate' => round($overallOccRate, 2),
                        'properties' => $metrics,
                        'summary' => [
                            'total_properties' => count($metrics),
                            'total_booked_nights' => $totalBooked,
                            'total_available_nights' => $totalAvailable,
                            'total_revenue' => array_sum(array_column($metrics, 'room_revenue'))
                        ]
                    ];

                    // Add room filter info if specified
                    if ($room) {
                        $response['room_filter'] = $room;
                    }

                    $this->sendResponse($response);
                    break;

                case 'property':
                    $propertyName = $_GET['property'] ?? '';
                    if (empty($propertyName)) {
                        $this->sendResponse(['error' => 'Property name required'], 400);
                        return;
                    }

                    $metrics = $this->getPropertyMetrics($propertyName, $year, $month, $room);
                    if ($metrics === null) {
                        $this->sendResponse(['error' => 'Property not found or no data'], 404);
                        return;
                    }

                    $this->sendResponse($metrics);
                    break;

                case 'iwatoyama_rooms':
                    $roomMetrics = $this->getPropertyRoomMetrics('iwatoyama', $year, $month);
                    $this->sendResponse([
                        'year' => $year,
                        'month' => $month,
                        'property' => 'iwatoyama',
                        'rooms' => $roomMetrics
                    ]);
                    break;

                case 'goettingen_rooms':
                    $roomMetrics = $this->getPropertyRoomMetrics('goettingen', $year, $month);
                    $this->sendResponse([
                        'year' => $year,
                        'month' => $month,
                        'property' => 'goettingen',
                        'rooms' => $roomMetrics
                    ]);
                    break;

                case 'littlehouse_rooms':
                    $roomMetrics = $this->getPropertyRoomMetrics('littlehouse', $year, $month);
                    $this->sendResponse([
                        'year' => $year,
                        'month' => $month,
                        'property' => 'littlehouse',
                        'rooms' => $roomMetrics
                    ]);
                    break;

                case 'kaguya_rooms':
                    $roomMetrics = $this->getPropertyRoomMetrics('kaguya', $year, $month);
                    $this->sendResponse([
                        'year' => $year,
                        'month' => $month,
                        'property' => 'kaguya',
                        'rooms' => $roomMetrics
                    ]);
                    break;

                case 'last_import_time':
                    // Get the most recent last_imported time from property_sheets
                    $stmt = $this->pdo->query("SELECT MAX(last_imported) as last_import_time FROM property_sheets WHERE is_active = TRUE");
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    $this->sendResponse([
                        'last_import_time' => $result['last_import_time']
                    ]);
                    break;

                case 'daily_occupancy':
                    // Get daily occupancy rates for a date range
                    $startDate = $_GET['start_date'] ?? date('Y-m-01'); // Default to first day of current month
                    $endDate = $_GET['end_date'] ?? date('Y-m-d'); // Default to today
                    $dailyData = $this->getDailyOccupancyRates($startDate, $endDate);

                    // Calculate year-to-date revenue for each date
                    $yearStart = date('Y-01-01', strtotime($startDate));
                    $dailyYTDRevenue = [];
                    $dailyDifferences = [];
                    $previousYTD = null;

                    foreach ($dailyData['dates'] as $date) {
                        // Get YTD revenue up to this date
                        $stmt = $this->pdo->prepare("
                            SELECT SUM(room_revenue) as total_revenue
                            FROM daily_occupancy_records
                            WHERE record_date BETWEEN ? AND ?
                        ");
                        $stmt->execute([$yearStart, $date]);
                        $ytdRevenue = floatval($stmt->fetch(PDO::FETCH_ASSOC)['total_revenue'] ?? 0);

                        $dailyYTDRevenue[$date] = $ytdRevenue;

                        // Calculate difference from previous day
                        if ($previousYTD !== null) {
                            $dailyDifferences[$date] = $ytdRevenue - $previousYTD;
                        } else {
                            $dailyDifferences[$date] = 0; // First day has no previous
                        }

                        $previousYTD = $ytdRevenue;
                    }

                    // Add to response
                    $dailyData['daily_ytd_revenue'] = $dailyYTDRevenue;
                    $dailyData['daily_differences'] = $dailyDifferences;

                    $this->sendResponse($dailyData);
                    break;

                case '180_day_limit':
                    // Get 180-day limit data for properties
                    $fiscalYearData = $this->get180DayLimitData();
                    $this->sendResponse($fiscalYearData);
                    break;

                default:
                    $this->sendResponse(['error' => 'Unknown action'], 404);
            }
        } catch (Exception $e) {
            $this->sendResponse(['error' => $e->getMessage()], 500);
        }
    }

    private function sendResponse($data, $statusCode = 200) {
        http_response_code($statusCode);
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// Initialize and run the API
try {
    $api = new OccupancyMetricsAPI();
    $api->route();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error', 'message' => $e->getMessage()]);
}
?>