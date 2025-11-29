'use client';

import React, { useState, useEffect, useCallback } from 'react';
import { useRouter } from 'next/navigation';
import { Calendar, Calculator, Download, RefreshCw, Settings, ChevronDown, ChevronRight, BarChart3, Database, Calendar as CalendarIcon, Clock, Users } from 'lucide-react';
import VerticalDisplay from './VerticalDisplay';
import CommissionBreakdownModal from './CommissionBreakdownModal';
import OccRecordTab from './OccRecordTab';
import Day180LimitTab from './Day180LimitTab';
import AdminPropertiesOwnerView from './AdminPropertiesOwnerView';

interface CommissionBreakdown {
  checkout_cleaning_fee_per_booking?: number;
  checkout_cleaning_count?: number;
  total_checkout_cleaning?: number;
  checkout_cleaning_dates?: string[];
  stay_cleaning_fee_per_cleaning?: number;
  stay_cleaning_count?: number;
  total_stay_cleaning?: number;
  stay_cleaning_dates?: string[];
  total_all_cleaning?: number;
  average_cleaning_per_booking?: number;
  linen_fee_per_person?: number;
  total_people?: number;
  total_linen_fee?: number;
  operation_management_fee_monthly?: number;
  monthly_inspection_fee?: number;
  emergency_staff_fee_monthly?: number;
  garbage_collection_fee_monthly?: number;
  total_variable_fees?: number;
}

interface PropertyMetrics {
  property_name: string;
  booked_nights: number;
  booking_count: number;
  available_rooms: number;
  sold_rooms: number;
  room_revenue: number;
  occ_rate: number;
  adr: number;
  revpar: number;
  cleaning_fee_per_time: number;
  total_cleaning_fee: number;
  ota_commission: number;
  commission_percent: number;
  agency_fee: number;
  avg_lead_time: number;
  room_type?: string;
  commission_method?: string;
  commission_breakdown?: CommissionBreakdown;
  total_people?: number;
  total_stay_cleanings?: number;
}

export default function OccupancyDashboard() {
  const router = useRouter();
  const [authenticated, setAuthenticated] = useState(false);
  const [viewMode, setViewMode] = useState<'horizontal' | 'vertical' | 'properties_owners' | 'occ_record' | '180_day_limit'>('horizontal');
  const [selectedYear, setSelectedYear] = useState(new Date().getFullYear());
  const [selectedMonth, setSelectedMonth] = useState(new Date().getMonth() + 1);
  const [metrics, setMetrics] = useState<PropertyMetrics[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [importing, setImporting] = useState(false);
  const [importMessage, setImportMessage] = useState<{ type: 'success' | 'error' | 'info'; message: string } | null>(null);
  const [lastImportTime, setLastImportTime] = useState<string | null>(null);
  const [editingCommission, setEditingCommission] = useState<{ [key: string]: boolean }>({});
  const [editingCleaningFee, setEditingCleaningFee] = useState<{ [key: string]: boolean }>({});
  const [showSettings, setShowSettings] = useState(false);
  const [expandedIwatoyama, setExpandedIwatoyama] = useState(false);
  const [iwatoyamaRooms, setIwatoyamaRooms] = useState<PropertyMetrics[]>([]);
  const [expandedGoettingen, setExpandedGoettingen] = useState(false);
  const [goettingenRooms, setGoettingenRooms] = useState<PropertyMetrics[]>([]);
  const [expandedLittlehouse, setExpandedLittlehouse] = useState(false);
  const [littlehouseRooms, setLittlehouseRooms] = useState<PropertyMetrics[]>([]);
  const [expandedKaguya, setExpandedKaguya] = useState(false);
  const [kaguyaRooms, setKaguyaRooms] = useState<PropertyMetrics[]>([]);
  const [loadingRooms, setLoadingRooms] = useState<{ [key: string]: boolean }>({});
  const [commissionSettings, setCommissionSettings] = useState<{ [key: string]: number }>({
    'comodita': 15,
    'mujurin': 15,
    'fujinomori': 15,
    'enraku': 15,
    'tsubaki': 15,
    'hiiragi': 15,
    'fushimi_apt': 15,
    'kanon': 15,
    'fushimi_house': 15,
    'kado': 15,
    'tanuki': 15,
    'fukuro': 15,
    'hauwa_apt': 15,
    'littlehouse': 25,
    'yanagawa': 15,
    'nishijin_fujita': 15,
    'rikyu': 15,
    'hiroshima': 15,
    'okinawa': 15,
    'iwatoyama': 58,
    'goettingen': 25,
    'ryoma': 50,
    'isa': 15,
    'yura': 15,
    'konoha': 15,
    'kaguya': 25,
    'default': 15
  });
  const [cleaningFeeSettings, setCleaningFeeSettings] = useState<{ [key: string]: number }>({
    'comodita': 6050,
    'mujurin': 13200,
    'fujinomori': 7150,
    'enraku': 4950,
    'tsubaki': 8800,
    'hiiragi': 8800,
    'fushimi_apt': 6500,
    'kanon': 4500,
    'fushimi_house': 6000,
    'kado': 6500,
    'tanuki': 6500,
    'fukuro': 6500,
    'hauwa_apt': 0,
    'littlehouse': 4200,
    'yanagawa': 0,
    'nishijin_fujita': 0,
    'rikyu': 6600,
    'hiroshima': 0,
    'okinawa': 0,
    'iwatoyama': 4000,
    'goettingen': 4000,
    'ryoma': 0,
    'isa': 0,
    'yura': 0,
    'konoha': 8000,
    'kaguya': 4000,
    'default': 5000
  });
  const [summary, setSummary] = useState({
    totalOccRate: '0%',
    targetOccRate: '30%'
  });
  const [showBreakdownModal, setShowBreakdownModal] = useState(false);
  const [selectedPropertyData, setSelectedPropertyData] = useState<PropertyMetrics | null>(null);

  const handleShowBreakdown = (metric: PropertyMetrics) => {
    if (metric.commission_method === 'fixed' || metric.commission_method === 'kaguya_monthly') {
      setSelectedPropertyData(metric);
      setShowBreakdownModal(true);
    }
  };

  const fetchPropertyRooms = async (propertyName: string) => {
    const isIwatoyama = propertyName === 'iwatoyama';
    const isGoettingen = propertyName === 'Goettingen';
    const isLittlehouse = propertyName === 'littlehouse';
    const isKaguya = propertyName === 'kaguya';

    const existingRooms = isIwatoyama ? iwatoyamaRooms : isGoettingen ? goettingenRooms : isLittlehouse ? littlehouseRooms : isKaguya ? kaguyaRooms : [];

    if (existingRooms.length > 0) {
      // Already loaded, just toggle
      if (isIwatoyama) {
        setExpandedIwatoyama(!expandedIwatoyama);
      } else if (isGoettingen) {
        setExpandedGoettingen(!expandedGoettingen);
      } else if (isLittlehouse) {
        setExpandedLittlehouse(!expandedLittlehouse);
      } else if (isKaguya) {
        setExpandedKaguya(!expandedKaguya);
      }
      return;
    }

    setLoadingRooms(prev => ({ ...prev, [propertyName]: true }));
    try {
      const phpApiUrl = process.env.NEXT_PUBLIC_PHP_API_URL || 'https://exseed.main.jp/WG/analysis/OCC/occupancy_metrics_api.php';
      const response = await fetch(`${phpApiUrl}?action=${propertyName.toLowerCase()}_rooms&year=${selectedYear}&month=${selectedMonth}`);
      const data = await response.json();

      if (data.error) {
        console.error(`Error fetching ${propertyName} rooms:`, data.error);
      } else {
        // Use values directly from API (which come from database)
        const rooms = data.rooms || [];

        if (isIwatoyama) {
          setIwatoyamaRooms(rooms);
          setExpandedIwatoyama(true);
        } else if (isGoettingen) {
          setGoettingenRooms(rooms);
          setExpandedGoettingen(true);
        } else if (isLittlehouse) {
          setLittlehouseRooms(rooms);
          setExpandedLittlehouse(true);
        } else if (isKaguya) {
          setKaguyaRooms(rooms);
          setExpandedKaguya(true);
        }
      }
    } catch (error) {
      console.error(`Error fetching ${propertyName} rooms:`, error);
    } finally {
      setLoadingRooms(prev => ({ ...prev, [propertyName]: false }));
    }
  };

  const fetchLastImportTime = async () => {
    try {
      const phpApiUrl = process.env.NEXT_PUBLIC_PHP_API_URL || 'https://exseed.main.jp/WG/analysis/OCC/occupancy_metrics_api.php';
      const response = await fetch(`${phpApiUrl}?action=last_import_time`);
      const data = await response.json();
      if (data.last_import_time) {
        setLastImportTime(data.last_import_time);
      }
    } catch (error) {
      console.error('Error fetching last import time:', error);
    }
  };

  const calculateMetrics = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const phpApiUrl = process.env.NEXT_PUBLIC_PHP_API_URL || 'https://exseed.main.jp/WG/analysis/OCC/occupancy_metrics_api.php';
      const apiUrl = `${phpApiUrl}?year=${selectedYear}&month=${selectedMonth}`;
      const response = await fetch(apiUrl);
      const data = await response.json();

      // Fetch last import time
      fetchLastImportTime();

      if (data.error) {
        setError(data.error);
        setMetrics([]);
        setSummary({
          totalOccRate: '0%',
          targetOccRate: '30%'
        });
      } else {
        // Use values directly from API (which come from database)
        setMetrics(data.properties || []);
        // Update summary with real data from API
        setSummary({
          totalOccRate: `${data.overall_occupancy_rate?.toFixed(2) || '0'}%`,
          targetOccRate: '30%'
        });
      }
    } catch (error) {
      console.error('Error calculating metrics:', error);
      setError('Failed to connect to API. Please check your connection.');
      setMetrics([]);
    } finally {
      setLoading(false);
    }
  }, [selectedYear, selectedMonth]);

  const checkAuth = useCallback(async () => {
    try {
      const response = await fetch('https://exseed.main.jp/WG/analysis/OCC/auth_api.php?action=check', {
        credentials: 'include',
      });
      const data = await response.json();

      if (!data.authenticated) {
        router.push('/login');
        return;
      }

      if (data.user.user_type !== 'admin') {
        router.push('/property-dashboard');
        return;
      }

      setAuthenticated(true);
    } catch (err) {
      console.error('Auth check failed:', err);
      router.push('/login');
    }
  }, [router]);

  useEffect(() => {
    checkAuth();
  }, [checkAuth]);

  const handleLogout = async () => {
    try {
      await fetch('https://exseed.main.jp/WG/analysis/OCC/auth_api.php?action=logout', {
        credentials: 'include',
      });
      sessionStorage.removeItem('user');
      router.push('/login');
    } catch (err) {
      console.error('Logout failed:', err);
    }
  };

  // Load settings first, then calculate metrics
  useEffect(() => {
    if (!authenticated) return;

    const loadSettings = async () => {
      const savedCommission = localStorage.getItem('exseedocc_commission_settings');
      if (savedCommission) {
        setCommissionSettings(JSON.parse(savedCommission));
      }

      const savedCleaningFee = localStorage.getItem('exseedocc_cleaning_fee_settings');
      if (savedCleaningFee) {
        setCleaningFeeSettings(JSON.parse(savedCleaningFee));
      }
    };

    loadSettings();
  }, [authenticated]);

  // Calculate metrics when year/month changes or settings are loaded
  useEffect(() => {
    if (authenticated) {
      calculateMetrics();
      // Reset property expansions when date changes
      setExpandedIwatoyama(false);
      setIwatoyamaRooms([]);
      setExpandedGoettingen(false);
      setGoettingenRooms([]);
      setExpandedLittlehouse(false);
      setLittlehouseRooms([]);
      setExpandedKaguya(false);
      setKaguyaRooms([]);
    }
  }, [authenticated, selectedYear, selectedMonth, calculateMetrics]);

  // Recalculate when settings change
  useEffect(() => {
    if (authenticated && metrics.length > 0) {
      calculateMetrics();
    }
  }, [authenticated, commissionSettings, cleaningFeeSettings, calculateMetrics, metrics.length]);

  const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('ja-JP', { style: 'currency', currency: 'JPY' }).format(amount);
  };

  const formatPercent = (rate: number) => {
    return `${rate.toFixed(2)}%`;
  };

  const getOccRateColor = (rate: number) => {
    if (rate >= 80) return 'text-green-700';
    if (rate >= 60) return 'text-yellow-600';
    if (rate >= 40) return 'text-orange-600';
    return 'text-red-700';
  };

  const handleCommissionEdit = (propertyName: string, newCommission: number) => {
    // Update commission settings and save to localStorage
    const updatedCommissionSettings = { ...commissionSettings, [propertyName]: newCommission };
    setCommissionSettings(updatedCommissionSettings);
    localStorage.setItem('exseedocc_commission_settings', JSON.stringify(updatedCommissionSettings));

    setMetrics(prevMetrics =>
      prevMetrics.map(metric => {
        if (metric.property_name === propertyName) {
          // Recalculate commission-related values
          const newOtaCommission = metric.room_revenue * (newCommission / 100);
          const newAgencyFee = newOtaCommission; // Same as OTA commission

          return {
            ...metric,
            commission_percent: newCommission,
            ota_commission: newOtaCommission,
            agency_fee: newAgencyFee
          };
        }
        return metric;
      })
    );
    setEditingCommission(prev => ({ ...prev, [propertyName]: false }));
  };

  const toggleCommissionEdit = (propertyName: string) => {
    setEditingCommission(prev => ({ ...prev, [propertyName]: !prev[propertyName] }));
  };

  const handleCleaningFeeEdit = (propertyName: string, newFee: number) => {
    // Update cleaning fee settings and save to localStorage
    const updatedCleaningFeeSettings = { ...cleaningFeeSettings, [propertyName]: newFee };
    setCleaningFeeSettings(updatedCleaningFeeSettings);
    localStorage.setItem('exseedocc_cleaning_fee_settings', JSON.stringify(updatedCleaningFeeSettings));

    setMetrics(prevMetrics =>
      prevMetrics.map(metric => {
        if (metric.property_name === propertyName) {
          // Recalculate cleaning fee related values
          const newTotalCleaningFee = metric.booking_count * newFee;

          return {
            ...metric,
            cleaning_fee_per_time: newFee,
            total_cleaning_fee: newTotalCleaningFee
          };
        }
        return metric;
      })
    );
    setEditingCleaningFee(prev => ({ ...prev, [propertyName]: false }));
  };

  const toggleCleaningFeeEdit = (propertyName: string) => {
    setEditingCleaningFee(prev => ({ ...prev, [propertyName]: !prev[propertyName] }));
  };

  const saveCommissionSettings = () => {
    // Save both commission and cleaning fee settings to localStorage
    localStorage.setItem('exseedocc_commission_settings', JSON.stringify(commissionSettings));
    localStorage.setItem('exseedocc_cleaning_fee_settings', JSON.stringify(cleaningFeeSettings));

    // Apply new settings to current metrics
    setMetrics(prevMetrics =>
      prevMetrics.map(metric => {
        const newCommission = commissionSettings[metric.property_name] || commissionSettings['default'];
        const newCleaningFee = cleaningFeeSettings[metric.property_name] || cleaningFeeSettings['default'];

        const newOtaCommission = metric.room_revenue * (newCommission / 100);
        const newAgencyFee = newOtaCommission;
        const newTotalCleaningFee = metric.booking_count * newCleaningFee;

        return {
          ...metric,
          commission_percent: newCommission,
          ota_commission: newOtaCommission,
          agency_fee: newAgencyFee,
          cleaning_fee_per_time: newCleaningFee,
          total_cleaning_fee: newTotalCleaningFee
        };
      })
    );

    setShowSettings(false);
  };

  const updateCommissionSetting = (property: string, value: number) => {
    setCommissionSettings(prev => ({ ...prev, [property]: value }));
  };

  const updateCleaningFeeSetting = (property: string, value: number) => {
    setCleaningFeeSettings(prev => ({ ...prev, [property]: value }));
  };

  const runManualImport = async () => {
    setImporting(true);
    setImportMessage({ type: 'info', message: 'Starting import from Google Sheets... This may take 30-60 seconds.' });

    try {
      // Call external API directly
      const importUrl = 'https://exseed.main.jp/WG/analysis/OCC/auto_import_cron.php?auth_key=exseed_auto_import_2025';
      const response = await fetch(importUrl);
      const data = await response.json();

      if (data.success) {
        setImportMessage({
          type: 'success',
          message: '✅ Import completed successfully! All data has been synced from Google Sheets. Refreshing dashboard...'
        });
        // Fetch new import time and wait 2 seconds then refresh metrics
        setTimeout(() => {
          fetchLastImportTime();
          calculateMetrics();
          setImportMessage(null);
        }, 2000);
      } else {
        setImportMessage({
          type: 'error',
          message: `❌ Import failed: ${data.message || data.error || 'Unknown error'}`
        });
      }
    } catch (error) {
      console.error('Import error:', error);
      setImportMessage({
        type: 'error',
        message: '❌ Failed to connect to import service. Please check your connection or try again later.'
      });
    } finally {
      setImporting(false);
    }
  };

  const exportToCSV = () => {
    const headers = [
      'Property', 'Booked Nights', 'Booking Count', 'Available Rooms', 'Sold Rooms', 'Room Revenue',
      'OCC', 'ADR', 'RevPAR', 'Cleaning Fee/Time', 'Total Cleaning', 'OTA Commission', 'Com%', 'Agency Fee', 'Avg Lead Time (days)'
    ];

    const csvContent = [
      headers.join(','),
      ...metrics.map(m => [
        m.property_name,
        m.booked_nights,
        m.booking_count,
        m.available_rooms,
        m.sold_rooms,
        m.room_revenue,
        formatPercent(m.occ_rate),
        formatCurrency(m.adr),
        formatCurrency(m.revpar),
        m.cleaning_fee_per_time,
        m.total_cleaning_fee,
        m.ota_commission,
        `${m.commission_percent}%`,
        m.agency_fee,
        m.avg_lead_time
      ].join(','))
    ].join('\n');

    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = `occupancy_report_${selectedYear}_${selectedMonth.toString().padStart(2, '0')}.csv`;
    link.click();
  };

  // Check if vertical mode should be shown
  if (viewMode === 'vertical') {
    return <VerticalDisplay selectedYear={selectedYear} onBack={() => setViewMode('horizontal')} />;
  }

  // Check if OCC Record mode
  if (viewMode === 'occ_record') {
    return (
      <div className="min-h-screen bg-gray-50">
        <div className="p-4">
          <button
            onClick={() => setViewMode('horizontal')}
            className="mb-4 px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200"
          >
            ← Back to Dashboard
          </button>
          <OccRecordTab />
        </div>
      </div>
    );
  }

  // Check if 180 Day Limit mode
  if (viewMode === '180_day_limit') {
    return (
      <div className="min-h-screen bg-gray-50">
        <div className="p-4">
          <button
            onClick={() => setViewMode('horizontal')}
            className="mb-4 px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200"
          >
            ← Back to Dashboard
          </button>
          <Day180LimitTab />
        </div>
      </div>
    );
  }

  // Properties Owners mode - show all properties with owner dashboard features
  if (viewMode === 'properties_owners') {
    return (
      <AdminPropertiesOwnerView
        onBack={() => setViewMode('horizontal')}
      />
    );
  }

  if (!authenticated) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-gray-50">
        <div className="text-center">
          <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto"></div>
          <p className="mt-4 text-gray-600">Verifying authentication...</p>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-gray-50 p-6">
      {/* Header */}
      <div className="bg-white rounded-lg shadow-sm border p-6 mb-6">
        <div className="flex items-center justify-between mb-4">
          <div className="flex items-center gap-3">
            <div className="bg-blue-600 p-2 rounded-lg">
              <Calculator className="h-6 w-6 text-white" />
            </div>
            <div>
              <h1 className="text-2xl font-bold text-gray-900">Occupancy Rate Calculation Dashboard (Admin)</h1>
              <p className="text-sm text-gray-600">Real-time property occupancy metrics and analytics</p>
              {lastImportTime && (
                <p className="text-xs text-gray-500 mt-1 flex items-center gap-1">
                  <span className="inline-block w-2 h-2 rounded-full bg-green-500 animate-pulse"></span>
                  Last updated from Google Sheets: {new Date(lastImportTime).toLocaleString('en-US', {
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit'
                  })}
                </p>
              )}
            </div>
          </div>
          <div className="flex items-center gap-4">
            <div className="flex items-center gap-2 text-lg font-semibold">
              <span className="text-green-600">{summary.totalOccRate}</span>
              <span className="text-gray-400">/</span>
              <span className="text-orange-500">{summary.targetOccRate}</span>
            </div>
            {/* View Mode Toggle */}
            <div className="flex gap-2">
              <button
                onClick={() => setViewMode('horizontal')}
                className={`flex items-center gap-2 px-4 py-2 rounded-lg transition-colors ${
                  (viewMode as string) === 'horizontal'
                    ? 'bg-blue-600 text-white'
                    : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                }`}
              >
                <Calculator className="h-4 w-4" />
                Dashboard
              </button>
              <button
                onClick={() => setViewMode('vertical')}
                className={`flex items-center gap-2 px-4 py-2 rounded-lg transition-colors ${
                  (viewMode as string) === 'vertical'
                    ? 'bg-purple-600 text-white'
                    : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                }`}
              >
                <BarChart3 className="h-4 w-4" />
                Vertical View
              </button>
              <button
                onClick={() => setViewMode('properties_owners')}
                className={`flex items-center gap-2 px-4 py-2 rounded-lg transition-colors ${
                  (viewMode as string) === 'properties_owners'
                    ? 'bg-green-600 text-white'
                    : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                }`}
              >
                <Users className="h-4 w-4" />
                Properties Owners
              </button>
              <button
                onClick={() => setViewMode('occ_record')}
                className={`flex items-center gap-2 px-4 py-2 rounded-lg transition-colors ${
                  (viewMode as string) === 'occ_record'
                    ? 'bg-orange-600 text-white'
                    : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                }`}
              >
                <CalendarIcon className="h-4 w-4" />
                OCC Record
              </button>
              <button
                onClick={() => setViewMode('180_day_limit')}
                className={`flex items-center gap-2 px-4 py-2 rounded-lg transition-colors ${
                  (viewMode as string) === '180_day_limit'
                    ? 'bg-indigo-600 text-white'
                    : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                }`}
              >
                <Clock className="h-4 w-4" />
                180-Day Limit
              </button>
            </div>
            <button
              onClick={handleLogout}
              className="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors flex items-center gap-2"
            >
              Logout
            </button>
          </div>
        </div>

        {/* Date Selection */}
        <div className="flex items-center gap-4">
          <div className="flex items-center gap-2">
            <Calendar className="h-5 w-5 text-gray-700" />
            <select
              value={selectedYear}
              onChange={(e) => setSelectedYear(parseInt(e.target.value))}
              className="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-gray-900 font-medium"
            >
              {Array.from({ length: 5 }, (_, i) => new Date().getFullYear() - 2 + i).map(year => (
                <option key={year} value={year}>{year}</option>
              ))}
            </select>
            <span className="text-lg font-semibold text-gray-900">Year</span>
          </div>

          <div className="flex items-center gap-2">
            <select
              value={selectedMonth}
              onChange={(e) => setSelectedMonth(parseInt(e.target.value))}
              className="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-gray-900 font-medium"
            >
              {Array.from({ length: 12 }, (_, i) => i + 1).map(month => (
                <option key={month} value={month}>{month}</option>
              ))}
            </select>
            <span className="text-lg font-semibold text-gray-900">Month</span>
          </div>


          <button
            onClick={calculateMetrics}
            disabled={loading}
            className="flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50"
          >
            <RefreshCw className={`h-4 w-4 ${loading ? 'animate-spin' : ''}`} />
            Calculate
          </button>

          <button
            onClick={runManualImport}
            disabled={importing}
            className="flex items-center gap-2 px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 disabled:opacity-50 disabled:cursor-not-allowed"
            title="Import latest data from Google Sheets"
          >
            <Database className={`h-4 w-4 ${importing ? 'animate-pulse' : ''}`} />
            {importing ? 'Importing...' : 'Import Data'}
          </button>

          <button
            onClick={exportToCSV}
            className="flex items-center gap-2 px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700"
          >
            <Download className="h-4 w-4" />
            CSV Export
          </button>

          <button
            onClick={() => setShowSettings(true)}
            className="flex items-center gap-2 px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700"
          >
            <Settings className="h-4 w-4" />
            Settings
          </button>
        </div>
      </div>

      {/* Import Message */}
      {importMessage && (
        <div className={`rounded-lg p-4 mb-6 ${
          importMessage.type === 'success' ? 'bg-green-50 border border-green-200' :
          importMessage.type === 'error' ? 'bg-red-50 border border-red-200' :
          'bg-blue-50 border border-blue-200'
        }`}>
          <div className="flex items-center gap-2">
            <div className={`h-5 w-5 rounded-full flex items-center justify-center ${
              importMessage.type === 'success' ? 'bg-green-500' :
              importMessage.type === 'error' ? 'bg-red-500' :
              'bg-blue-500'
            }`}>
              <span className="text-white text-xs">
                {importMessage.type === 'success' ? '✓' : importMessage.type === 'error' ? '!' : 'i'}
              </span>
            </div>
            <span className={`font-medium ${
              importMessage.type === 'success' ? 'text-green-800' :
              importMessage.type === 'error' ? 'text-red-800' :
              'text-blue-800'
            }`}>
              {importMessage.type === 'success' ? 'Import Successful' :
               importMessage.type === 'error' ? 'Import Failed' :
               'Importing'}
            </span>
          </div>
          <p className={`text-sm mt-1 ${
            importMessage.type === 'success' ? 'text-green-700' :
            importMessage.type === 'error' ? 'text-red-700' :
            'text-blue-700'
          }`}>
            {importMessage.message}
          </p>
        </div>
      )}

      {/* Error Message */}
      {error && (
        <div className="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
          <div className="flex items-center gap-2">
            <div className="h-5 w-5 rounded-full bg-red-500 flex items-center justify-center">
              <span className="text-white text-xs">!</span>
            </div>
            <span className="font-medium text-red-800">API Connection Error</span>
          </div>
          <p className="text-sm text-red-700 mt-1">{error}</p>
          <p className="text-sm text-red-600 mt-2">
            📋 To fix this: Upload <code>occupancy_metrics_api.php</code> to your server at <code>https://exseed.main.jp/WG/analysis/OCC/</code>
          </p>
        </div>
      )}

      {/* Settings Modal */}
      {showSettings && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
          <div className="bg-white rounded-lg p-6 w-full max-w-2xl max-h-[80vh] overflow-y-auto">
            <div className="flex items-center justify-between mb-4">
              <h2 className="text-xl font-bold text-gray-900">Commission Settings</h2>
              <button
                onClick={() => setShowSettings(false)}
                className="text-gray-500 hover:text-gray-700"
              >
                ✕
              </button>
            </div>

            <div className="space-y-4">
              <p className="text-sm text-gray-600">
                Set commission rates and cleaning fees for each property. Settings are automatically saved and will be applied on your next visit.
              </p>

              {/* Commission Rates Section */}
              <h3 className="text-lg font-semibold text-gray-900 border-b pb-2">Commission Rates</h3>

              {/* All Properties */}
              <div className="grid grid-cols-1 md:grid-cols-3 gap-4 max-h-60 overflow-y-auto">
                {Object.keys(commissionSettings).filter(key => key !== 'default').sort().map(property => (
                  <div key={property} className="space-y-2">
                    <label className="block text-sm font-medium text-gray-700">
                      {property} (%)
                    </label>
                    <input
                      type="number"
                      value={commissionSettings[property]}
                      onChange={(e) => updateCommissionSetting(property, parseFloat(e.target.value) || 0)}
                      className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-gray-900 font-medium"
                    />
                  </div>
                ))}
              </div>

              {/* Default Rate */}
              <div className="border-t pt-4">
                <div className="space-y-2">
                  <label className="block text-sm font-medium text-gray-700">
                    Default Commission Rate for Other Properties (%)
                  </label>
                  <input
                    type="number"
                    value={commissionSettings.default}
                    onChange={(e) => updateCommissionSetting('default', parseFloat(e.target.value) || 0)}
                    className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-gray-900 font-medium"
                  />
                  <p className="text-xs text-gray-500">
                    Commission rate applied to all properties not listed above
                  </p>
                </div>
              </div>

              {/* Cleaning Fees Section */}
              <h3 className="text-lg font-semibold text-gray-900 border-b pb-2">Cleaning Fees</h3>

              <div className="grid grid-cols-1 md:grid-cols-3 gap-4 max-h-60 overflow-y-auto">
                {Object.keys(cleaningFeeSettings).filter(key => key !== 'default').sort().map(property => (
                  <div key={property} className="space-y-2">
                    <label className="block text-sm font-medium text-gray-700">
                      {property} (¥)
                    </label>
                    <input
                      type="number"
                      value={cleaningFeeSettings[property]}
                      onChange={(e) => updateCleaningFeeSetting(property, parseFloat(e.target.value) || 0)}
                      className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-gray-900 font-medium"
                    />
                  </div>
                ))}
              </div>

              {/* Default Cleaning Fee */}
              <div className="border-t pt-4">
                <div className="space-y-2">
                  <label className="block text-sm font-medium text-gray-700">
                    Default Cleaning Fee for Other Properties (¥)
                  </label>
                  <input
                    type="number"
                    value={cleaningFeeSettings.default}
                    onChange={(e) => updateCleaningFeeSetting('default', parseFloat(e.target.value) || 0)}
                    className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-gray-900 font-medium"
                  />
                  <p className="text-xs text-gray-500">
                    Cleaning fee applied to all properties not listed above
                  </p>
                </div>
              </div>

              {/* Action Buttons */}
              <div className="flex gap-3 pt-4">
                <button
                  onClick={saveCommissionSettings}
                  className="flex-1 bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 font-medium"
                >
                  Save and Apply
                </button>
                <button
                  onClick={() => setShowSettings(false)}
                  className="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50"
                >
                  Cancel
                </button>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Metrics Table */}
      <div className="bg-white rounded-lg shadow-sm border overflow-hidden">
        <div className="overflow-auto max-h-[80vh]">
          <table className="w-full text-sm">
            <thead className="bg-gray-50 border-b sticky top-0 z-20">
              <tr>
                <th className="px-4 py-3 text-left font-medium text-gray-900 sticky left-0 bg-gray-50 z-30 border-r border-gray-200">Property</th>
                <th className="px-4 py-3 text-right font-medium text-gray-900">Booked<br/>Nights</th>
                <th className="px-4 py-3 text-right font-medium text-gray-900">Booking<br/>Count</th>
                <th className="px-4 py-3 text-right font-medium text-gray-900">Available<br/>Rooms<br/>1 night = 1 room</th>
                <th className="px-4 py-3 text-right font-medium text-gray-900">Sold<br/>Rooms<br/>1 night = 1 sale</th>
                <th className="px-4 py-3 text-right font-medium text-gray-900">Room<br/>Revenue</th>
                <th className="px-4 py-3 text-right font-medium text-gray-900">OCC</th>
                <th className="px-4 py-3 text-right font-medium text-gray-900">ADR</th>
                <th className="px-4 py-3 text-right font-medium text-gray-900">RevPAR</th>
                <th className="px-4 py-3 text-right font-medium text-gray-900">Cleaning<br/>Fee/Time</th>
                <th className="px-4 py-3 text-right font-medium text-gray-900">Total<br/>Cleaning</th>
                <th className="px-4 py-3 text-right font-medium text-gray-900">OTA<br/>Commission</th>
                <th className="px-4 py-3 text-right font-medium text-gray-900">Com%</th>
                <th className="px-4 py-3 text-right font-medium text-gray-900">Agency<br/>Fee</th>
                <th className="px-4 py-3 text-right font-medium text-gray-900">Avg Lead Time<br/>(days)</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-200">
              {loading ? (
                <tr>
                  <td colSpan={15} className="px-4 py-12 text-center text-gray-500">
                    <RefreshCw className="h-6 w-6 animate-spin mx-auto mb-2" />
                    Calculating...
                  </td>
                </tr>
              ) : metrics.length === 0 ? (
                <tr>
                  <td colSpan={15} className="px-4 py-12 text-center text-gray-500">
                    No data available
                  </td>
                </tr>
              ) : (
                <>
                  {metrics.map((metric, index) => (
                    <React.Fragment key={index}>
                      <tr className="hover:bg-gray-50">
                        <td className="px-4 py-3 font-medium text-gray-900 sticky left-0 bg-white z-10 border-r border-gray-200">
                          {(metric.property_name === 'iwatoyama' || metric.property_name === 'Goettingen' || metric.property_name === 'littlehouse' || metric.property_name === 'kaguya') ? (
                            <button
                              onClick={() => fetchPropertyRooms(metric.property_name)}
                              className="flex items-center gap-2 hover:text-blue-600 transition-colors"
                              disabled={loadingRooms[metric.property_name]}
                            >
                              {loadingRooms[metric.property_name] ? (
                                <RefreshCw className="h-4 w-4 animate-spin" />
                              ) : (metric.property_name === 'iwatoyama' ? expandedIwatoyama : (metric.property_name === 'Goettingen' ? expandedGoettingen : (metric.property_name === 'littlehouse' ? expandedLittlehouse : (metric.property_name === 'kaguya' ? expandedKaguya : false)))) ? (
                                <ChevronDown className="h-4 w-4" />
                              ) : (
                                <ChevronRight className="h-4 w-4" />
                              )}
                              {metric.property_name}
                            </button>
                          ) : (
                            metric.property_name
                          )}
                        </td>
                        <td className="px-4 py-3 text-right font-medium text-gray-900">{metric.booked_nights}</td>
                        <td className="px-4 py-3 text-right font-medium text-gray-900">{metric.booking_count}</td>
                        <td className="px-4 py-3 text-right font-medium text-gray-900">{metric.available_rooms}</td>
                        <td className="px-4 py-3 text-right font-medium text-gray-900">{metric.sold_rooms}</td>
                        <td className="px-4 py-3 text-right font-medium text-gray-900">{formatCurrency(metric.room_revenue)}</td>
                        <td className="px-4 py-3 text-right font-bold">
                          <span className={getOccRateColor(metric.occ_rate)}>
                            {formatPercent(metric.occ_rate)}
                          </span>
                        </td>
                        <td className="px-4 py-3 text-right font-medium text-gray-900">{formatCurrency(metric.adr)}</td>
                        <td className="px-4 py-3 text-right font-medium text-gray-900">{formatCurrency(metric.revpar)}</td>
                        <td className="px-4 py-3 text-right">
                          {metric.commission_method === 'kaguya_monthly' ? (
                            <span className="font-medium text-purple-600 italic" title="Kaguya uses monthly commission">
                              N/A
                            </span>
                          ) : metric.commission_method === 'fixed' ? (
                            <span
                              className="font-medium text-blue-600 cursor-pointer hover:bg-blue-50 px-2 py-1 rounded underline"
                              onClick={() => handleShowBreakdown(metric)}
                              title="Click to view breakdown"
                            >
                              {metric.cleaning_fee_per_time.toFixed(2)}
                            </span>
                          ) : editingCleaningFee[metric.property_name] ? (
                            <input
                              type="number"
                              defaultValue={metric.cleaning_fee_per_time}
                              className="w-20 px-2 py-1 text-center border border-gray-300 rounded font-medium text-gray-900"
                              onBlur={(e) => handleCleaningFeeEdit(metric.property_name, parseFloat(e.target.value) || metric.cleaning_fee_per_time)}
                              onKeyDown={(e) => {
                                if (e.key === 'Enter') {
                                  handleCleaningFeeEdit(metric.property_name, parseFloat(e.currentTarget.value) || metric.cleaning_fee_per_time);
                                }
                              }}
                              autoFocus
                            />
                          ) : (
                            <span
                              className="font-medium text-gray-900 cursor-pointer hover:bg-gray-100 px-1 py-1 rounded"
                              onClick={() => toggleCleaningFeeEdit(metric.property_name)}
                              title="Click to edit"
                            >
                              {metric.cleaning_fee_per_time.toFixed(2)}
                            </span>
                          )}
                        </td>
                        <td className="px-4 py-3 text-right">
                          {metric.commission_method === 'kaguya_monthly' ? (
                            <span className="font-medium text-purple-600 italic" title="Kaguya uses monthly commission">
                              N/A
                            </span>
                          ) : metric.commission_method === 'fixed' ? (
                            <span
                              className="font-medium text-blue-600 cursor-pointer hover:bg-blue-50 px-2 py-1 rounded underline"
                              onClick={() => handleShowBreakdown(metric)}
                              title="Click to view breakdown"
                            >
                              {metric.total_cleaning_fee.toFixed(2)}
                            </span>
                          ) : (
                            <span className="font-medium text-gray-900">
                              {metric.total_cleaning_fee.toFixed(2)}
                            </span>
                          )}
                        </td>
                        <td className="px-4 py-3 text-right">
                          {metric.commission_method === 'kaguya_monthly' ? (
                            <span
                              className="font-medium text-purple-600 cursor-pointer hover:bg-purple-50 px-2 py-1 rounded underline"
                              onClick={() => handleShowBreakdown(metric)}
                              title="Click to view Kaguya monthly commission"
                            >
                              {metric.ota_commission.toFixed(2)}
                            </span>
                          ) : metric.commission_method === 'fixed' ? (
                            <span
                              className="font-medium text-blue-600 cursor-pointer hover:bg-blue-50 px-2 py-1 rounded underline"
                              onClick={() => handleShowBreakdown(metric)}
                              title="Click to view breakdown"
                            >
                              {metric.ota_commission.toFixed(2)}
                            </span>
                          ) : (
                            <span className="font-medium text-gray-900">
                              {metric.ota_commission.toFixed(2)}
                            </span>
                          )}
                        </td>
                        <td className="px-4 py-3 text-right">
                          {metric.commission_method === 'kaguya_monthly' ? (
                            <span
                              className="font-bold text-purple-700 cursor-pointer hover:bg-purple-50 px-2 py-1 rounded underline"
                              onClick={() => handleShowBreakdown(metric)}
                              title="Click to view Kaguya monthly commission details"
                            >
                              {metric.commission_percent.toFixed(2)}%
                            </span>
                          ) : metric.commission_method === 'fixed' ? (
                            <span
                              className="font-medium text-blue-600 cursor-pointer hover:bg-blue-50 px-2 py-1 rounded underline"
                              onClick={() => handleShowBreakdown(metric)}
                              title="Click to view breakdown"
                            >
                              {metric.commission_percent.toFixed(2)}%
                            </span>
                          ) : editingCommission[metric.property_name] ? (
                            <input
                              type="number"
                              defaultValue={metric.commission_percent}
                              className="w-16 px-2 py-1 text-center border border-gray-300 rounded font-medium text-gray-900"
                              onBlur={(e) => handleCommissionEdit(metric.property_name, parseFloat(e.target.value) || metric.commission_percent)}
                              onKeyDown={(e) => {
                                if (e.key === 'Enter') {
                                  handleCommissionEdit(metric.property_name, parseFloat(e.currentTarget.value) || metric.commission_percent);
                                }
                              }}
                              autoFocus
                            />
                          ) : (
                            <span
                              className="font-medium text-gray-900 cursor-pointer hover:bg-gray-100 px-1 py-1 rounded"
                              onClick={() => toggleCommissionEdit(metric.property_name)}
                              title="Click to edit"
                            >
                              {metric.commission_percent.toFixed(2)}%
                            </span>
                          )}
                        </td>
                        <td className="px-4 py-3 text-right font-medium text-gray-900">{metric.agency_fee.toLocaleString()}</td>
                        <td className="px-4 py-3 text-right font-medium text-gray-900">{metric.avg_lead_time}</td>
                      </tr>
                      {/* Show room details if expanded for iwatoyama and goettingen */}
                      {metric.property_name === 'iwatoyama' && expandedIwatoyama && (
                        <>
                          {iwatoyamaRooms.map((roomMetric, roomIndex) => (
                            <tr key={`${index}-iwatoyama-room-${roomIndex}`} className="bg-blue-50 hover:bg-blue-100">
                              <td className="px-4 py-3 font-medium text-gray-700 pl-8 sticky left-0 bg-blue-50 z-10 border-r border-gray-200">
                                ↳ {roomMetric.room_type || 'Unknown Room'}
                              </td>
                              <td className="px-4 py-3 text-right font-medium text-gray-900">{roomMetric.booked_nights}</td>
                              <td className="px-4 py-3 text-right font-medium text-gray-900">{roomMetric.booking_count}</td>
                              <td className="px-4 py-3 text-right font-medium text-gray-900">{roomMetric.available_rooms}</td>
                              <td className="px-4 py-3 text-right font-medium text-gray-900">{roomMetric.sold_rooms}</td>
                              <td className="px-4 py-3 text-right font-medium text-gray-900">{formatCurrency(roomMetric.room_revenue)}</td>
                              <td className="px-4 py-3 text-right font-bold">
                                <span className={getOccRateColor(roomMetric.occ_rate)}>
                                  {formatPercent(roomMetric.occ_rate)}
                                </span>
                              </td>
                              <td className="px-4 py-3 text-right font-medium text-gray-900">{formatCurrency(roomMetric.adr)}</td>
                              <td className="px-4 py-3 text-right font-medium text-gray-900">{formatCurrency(roomMetric.revpar)}</td>
                              <td className="px-4 py-3 text-right font-medium text-gray-900">{roomMetric.cleaning_fee_per_time.toFixed(2)}</td>
                              <td className="px-4 py-3 text-right font-medium text-gray-900">{roomMetric.total_cleaning_fee.toFixed(2)}</td>
                              <td className="px-4 py-3 text-right font-medium text-gray-900">{roomMetric.ota_commission.toFixed(2)}</td>
                              <td className="px-4 py-3 text-right font-medium text-gray-900">{roomMetric.commission_percent.toFixed(2)}%</td>
                              <td className="px-4 py-3 text-right font-medium text-gray-900">{roomMetric.agency_fee.toFixed(2)}</td>
                              <td className="px-4 py-3 text-right font-medium text-gray-900">{roomMetric.avg_lead_time}</td>
                            </tr>
                          ))}
                        </>
                      )}
                      {metric.property_name === 'Goettingen' && expandedGoettingen && (
                        <>
                          {goettingenRooms.map((roomMetric, roomIndex) => (
                            <tr key={`${index}-goettingen-room-${roomIndex}`} className="bg-green-50 hover:bg-green-100">
                              <td className="px-4 py-3 font-medium text-gray-700 pl-8 sticky left-0 bg-green-50 z-10 border-r border-gray-200">
                                ↳ {roomMetric.room_type || 'Unknown Room'}
                              </td>
                              <td className="px-4 py-3 text-right font-medium text-gray-900">{roomMetric.booked_nights}</td>
                              <td className="px-4 py-3 text-right font-medium text-gray-900">{roomMetric.booking_count}</td>
                              <td className="px-4 py-3 text-right font-medium text-gray-900">{roomMetric.available_rooms}</td>
                              <td className="px-4 py-3 text-right font-medium text-gray-900">{roomMetric.sold_rooms}</td>
                              <td className="px-4 py-3 text-right font-medium text-gray-900">{formatCurrency(roomMetric.room_revenue)}</td>
                              <td className="px-4 py-3 text-right font-bold">
                                <span className={getOccRateColor(roomMetric.occ_rate)}>
                                  {formatPercent(roomMetric.occ_rate)}
                                </span>
                              </td>
                              <td className="px-4 py-3 text-right font-medium text-gray-900">{formatCurrency(roomMetric.adr)}</td>
                              <td className="px-4 py-3 text-right font-medium text-gray-900">{formatCurrency(roomMetric.revpar)}</td>
                              <td className="px-4 py-3 text-right font-medium text-gray-900">{roomMetric.cleaning_fee_per_time.toFixed(2)}</td>
                              <td className="px-4 py-3 text-right font-medium text-gray-900">{roomMetric.total_cleaning_fee.toFixed(2)}</td>
                              <td className="px-4 py-3 text-right font-medium text-gray-900">{roomMetric.ota_commission.toFixed(2)}</td>
                              <td className="px-4 py-3 text-right font-medium text-gray-900">{roomMetric.commission_percent.toFixed(2)}%</td>
                              <td className="px-4 py-3 text-right font-medium text-gray-900">{roomMetric.agency_fee.toFixed(2)}</td>
                              <td className="px-4 py-3 text-right font-medium text-gray-900">{roomMetric.avg_lead_time}</td>
                            </tr>
                          ))}
                        </>
                      )}
                      {metric.property_name === 'littlehouse' && expandedLittlehouse && (
                        <>
                          {littlehouseRooms.map((roomMetric, roomIndex) => (
                            <tr key={`${index}-littlehouse-room-${roomIndex}`} className="bg-purple-50 hover:bg-purple-100">
                              <td className="px-4 py-3 font-medium text-gray-700 pl-8 sticky left-0 bg-purple-50 z-10 border-r border-gray-200">
                                ↳ {roomMetric.room_type || 'Unknown Room'}
                              </td>
                              <td className="px-4 py-3 text-right font-medium text-gray-900">{roomMetric.booked_nights}</td>
                              <td className="px-4 py-3 text-right font-medium text-gray-900">{roomMetric.booking_count}</td>
                              <td className="px-4 py-3 text-right font-medium text-gray-900">{roomMetric.available_rooms}</td>
                              <td className="px-4 py-3 text-right font-medium text-gray-900">{roomMetric.sold_rooms}</td>
                              <td className="px-4 py-3 text-right font-medium text-gray-900">{formatCurrency(roomMetric.room_revenue)}</td>
                              <td className="px-4 py-3 text-right font-bold">
                                <span className={getOccRateColor(roomMetric.occ_rate)}>
                                  {formatPercent(roomMetric.occ_rate)}
                                </span>
                              </td>
                              <td className="px-4 py-3 text-right font-medium text-gray-900">{formatCurrency(roomMetric.adr)}</td>
                              <td className="px-4 py-3 text-right font-medium text-gray-900">{formatCurrency(roomMetric.revpar)}</td>
                              <td className="px-4 py-3 text-right font-medium text-gray-900">{roomMetric.cleaning_fee_per_time.toFixed(2)}</td>
                              <td className="px-4 py-3 text-right font-medium text-gray-900">{roomMetric.total_cleaning_fee.toFixed(2)}</td>
                              <td className="px-4 py-3 text-right font-medium text-gray-900">{roomMetric.ota_commission.toFixed(2)}</td>
                              <td className="px-4 py-3 text-right font-medium text-gray-900">{roomMetric.commission_percent.toFixed(2)}%</td>
                              <td className="px-4 py-3 text-right font-medium text-gray-900">{roomMetric.agency_fee.toFixed(2)}</td>
                              <td className="px-4 py-3 text-right font-medium text-gray-900">{roomMetric.avg_lead_time}</td>
                            </tr>
                          ))}
                        </>
                      )}
                      {metric.property_name === 'kaguya' && expandedKaguya && (
                        <>
                          {kaguyaRooms.map((roomMetric, roomIndex) => (
                            <tr key={`${index}-kaguya-room-${roomIndex}`} className="bg-yellow-50 hover:bg-yellow-100">
                              <td className="px-4 py-3 font-medium text-gray-700 pl-8 sticky left-0 bg-yellow-50 z-10 border-r border-gray-200">
                                ↳ {roomMetric.room_type || 'Unknown Room'}
                              </td>
                              <td className="px-4 py-3 text-right font-medium text-gray-900">{roomMetric.booked_nights}</td>
                              <td className="px-4 py-3 text-right font-medium text-gray-900">{roomMetric.booking_count}</td>
                              <td className="px-4 py-3 text-right font-medium text-gray-900">{roomMetric.available_rooms}</td>
                              <td className="px-4 py-3 text-right font-medium text-gray-900">{roomMetric.sold_rooms}</td>
                              <td className="px-4 py-3 text-right font-medium text-gray-900">{formatCurrency(roomMetric.room_revenue)}</td>
                              <td className="px-4 py-3 text-right font-bold">
                                <span className={getOccRateColor(roomMetric.occ_rate)}>
                                  {formatPercent(roomMetric.occ_rate)}
                                </span>
                              </td>
                              <td className="px-4 py-3 text-right font-medium text-gray-900">{formatCurrency(roomMetric.adr)}</td>
                              <td className="px-4 py-3 text-right font-medium text-gray-900">{formatCurrency(roomMetric.revpar)}</td>
                              <td className="px-4 py-3 text-right font-medium text-gray-900">{roomMetric.cleaning_fee_per_time.toFixed(2)}</td>
                              <td className="px-4 py-3 text-right font-medium text-gray-900">{roomMetric.total_cleaning_fee.toFixed(2)}</td>
                              <td className="px-4 py-3 text-right font-medium text-gray-900">{roomMetric.ota_commission.toFixed(2)}</td>
                              <td className="px-4 py-3 text-right font-medium text-gray-900">{roomMetric.commission_percent.toFixed(2)}%</td>
                              <td className="px-4 py-3 text-right font-medium text-gray-900">{roomMetric.agency_fee.toFixed(2)}</td>
                              <td className="px-4 py-3 text-right font-medium text-gray-900">{roomMetric.avg_lead_time}</td>
                            </tr>
                          ))}
                        </>
                      )}
                    </React.Fragment>
                  ))}
                </>
              )}
            </tbody>
          </table>
        </div>
      </div>

      {/* Commission Breakdown Modal */}
      <CommissionBreakdownModal
        isOpen={showBreakdownModal}
        onClose={() => setShowBreakdownModal(false)}
        propertyName={selectedPropertyData?.property_name || ''}
        commissionData={selectedPropertyData}
      />
    </div>
  );
}