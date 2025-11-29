'use client';

import { X } from 'lucide-react';

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
  // Kaguya monthly commission fields
  total_sales?: number;
  owner_payment?: number;
  exseed_commission?: number;
  commission_percentage?: number;
  year?: number;
  month?: number;
  notes?: string;
  data_source?: string;
}

interface CommissionData {
  commission_method?: string;
  commission_breakdown?: CommissionBreakdown;
  commission_percent?: number;
  room_revenue?: number;
  booking_count?: number;
}

interface CommissionBreakdownModalProps {
  isOpen: boolean;
  onClose: () => void;
  propertyName: string;
  commissionData: CommissionData | null;
}

export default function CommissionBreakdownModal({
  isOpen,
  onClose,
  propertyName,
  commissionData
}: CommissionBreakdownModalProps) {
  if (!isOpen) return null;

  const breakdown = commissionData?.commission_breakdown;
  const isFixed = commissionData?.commission_method === 'fixed';
  const isKaguyaMonthly = commissionData?.commission_method === 'kaguya_monthly';

  if ((!isFixed && !isKaguyaMonthly) || !breakdown) {
    return null;
  }

  const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('ja-JP', { style: 'currency', currency: 'JPY' }).format(amount);
  };

  return (
    <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
      <div className="bg-white rounded-lg max-w-4xl w-full max-h-[90vh] overflow-y-auto">
        {/* Header */}
        <div className="sticky top-0 bg-white border-b px-6 py-4 flex items-center justify-between">
          <div>
            <h2 className="text-2xl font-bold text-gray-900">Commission Breakdown</h2>
            <p className="text-sm text-gray-600 mt-1">
              {propertyName} - {isKaguyaMonthly ? 'Monthly Commission Data' : 'Fixed Commission Details'}
            </p>
          </div>
          <button
            onClick={onClose}
            className="text-gray-400 hover:text-gray-600 transition-colors"
          >
            <X className="h-6 w-6" />
          </button>
        </div>

        {/* Content */}
        <div className="p-6 space-y-6">
          {/* Kaguya Monthly Commission View */}
          {isKaguyaMonthly && (
            <>
              {/* Summary Card */}
              <div className="bg-gradient-to-br from-purple-50 to-indigo-50 rounded-lg p-6 border border-purple-200">
                <h3 className="text-lg font-semibold text-gray-900 mb-4">Kaguya Monthly Commission Summary</h3>
                <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                  <div>
                    <p className="text-sm text-gray-600">Period</p>
                    <p className="text-2xl font-bold text-purple-600">
                      {breakdown.year}/{String(breakdown.month).padStart(2, '0')}
                    </p>
                  </div>
                  <div>
                    <p className="text-sm text-gray-600">Total Sales</p>
                    <p className="text-2xl font-bold text-blue-600">
                      {formatCurrency(breakdown.total_sales || 0)}
                    </p>
                  </div>
                  <div>
                    <p className="text-sm text-gray-600">Exseed Commission</p>
                    <p className="text-2xl font-bold text-green-600">
                      {formatCurrency(breakdown.exseed_commission || 0)}
                    </p>
                  </div>
                  <div>
                    <p className="text-sm text-gray-600">Commission %</p>
                    <p className="text-2xl font-bold text-orange-600">
                      {breakdown.commission_percentage?.toFixed(1)}%
                    </p>
                  </div>
                </div>
              </div>

              {/* Monthly Data Breakdown */}
              <div className="border rounded-lg p-6">
                <h3 className="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                  <span className="bg-purple-100 text-purple-800 px-3 py-1 rounded-full text-sm">
                    Monthly Financial Data
                  </span>
                </h3>

                <div className="space-y-4">
                  <div className="bg-purple-50 rounded-lg p-4">
                    <div className="flex items-center justify-between mb-3">
                      <h4 className="font-semibold text-gray-900">Total Sales (Total Sales)</h4>
                      <span className="text-lg font-bold text-purple-700">
                        {formatCurrency(breakdown.total_sales || 0)}
                      </span>
                    </div>
                    <div className="text-sm text-gray-600">
                      Total revenue generated for this month from all sources
                    </div>
                  </div>

                  <div className="bg-blue-50 rounded-lg p-4">
                    <div className="flex items-center justify-between mb-3">
                      <h4 className="font-semibold text-gray-900">Owner Payment</h4>
                      <span className="text-lg font-bold text-blue-700">
                        {formatCurrency(breakdown.owner_payment || 0)}
                      </span>
                    </div>
                    <div className="text-sm text-gray-600">
                      Amount paid to property owner
                    </div>
                  </div>

                  <div className="bg-green-50 rounded-lg p-4">
                    <div className="flex items-center justify-between mb-3">
                      <h4 className="font-semibold text-gray-900">Exseed Commission</h4>
                      <span className="text-lg font-bold text-green-700">
                        {formatCurrency(breakdown.exseed_commission || 0)}
                      </span>
                    </div>
                    <div className="text-sm text-gray-600">
                      Commission retained by Exseed for this month
                    </div>
                  </div>

                  <div className="bg-orange-50 rounded-lg p-4 border-2 border-orange-300">
                    <div className="flex items-center justify-between mb-3">
                      <h4 className="font-semibold text-gray-900">Commission Percentage</h4>
                      <span className="text-2xl font-bold text-orange-700">
                        {breakdown.commission_percentage?.toFixed(1)}%
                      </span>
                    </div>
                    <div className="text-sm text-gray-600">
                      Percentage of total sales retained as commission
                    </div>
                  </div>
                </div>
              </div>

              {/* Calculation Formula */}
              <div className="border-2 border-purple-300 rounded-lg p-6 bg-purple-50">
                <h3 className="text-lg font-semibold text-gray-900 mb-4">📊 Monthly Calculation</h3>
                <div className="space-y-3 text-sm">
                  <div className="bg-white rounded p-3">
                    <p className="font-semibold text-gray-900 mb-2">Total Sales Breakdown:</p>
                    <div className="font-mono text-xs space-y-1 text-gray-700">
                      <p>Total Sales = Owner Payment + Exseed Commission</p>
                      <p>{formatCurrency(breakdown.total_sales || 0)} = {formatCurrency(breakdown.owner_payment || 0)} + {formatCurrency(breakdown.exseed_commission || 0)}</p>
                    </div>
                  </div>

                  <div className="bg-white rounded p-3">
                    <p className="font-semibold text-gray-900 mb-2">Commission Percentage:</p>
                    <div className="font-mono text-xs space-y-1 text-gray-700">
                      <p>Commission % = (Exseed Commission ÷ Total Sales) × 100</p>
                      <p>Commission % = ({formatCurrency(breakdown.exseed_commission || 0)} ÷ {formatCurrency(breakdown.total_sales || 0)}) × 100</p>
                      <p className="text-orange-700 font-bold">Commission % = {breakdown.commission_percentage?.toFixed(1)}%</p>
                    </div>
                  </div>
                </div>
              </div>

              {/* Data Source Info */}
              <div className="bg-blue-50 border border-blue-200 rounded-lg p-4">
                <div className="flex items-start gap-2">
                  <div className="text-blue-600 mt-0.5">ℹ️</div>
                  <div>
                    <p className="font-semibold text-blue-900">Data Source</p>
                    <p className="text-sm text-blue-700 mt-1">
                      This data is imported monthly from the Kaguya commission spreadsheet.
                      {breakdown.notes && ` Period: ${breakdown.notes}`}
                    </p>
                    {breakdown.data_source && (
                      <p className="text-xs text-blue-600 mt-1 font-mono">
                        Source: {breakdown.data_source}
                      </p>
                    )}
                  </div>
                </div>
              </div>
            </>
          )}

          {/* Fixed Commission View (existing) */}
          {isFixed && (
            <>
          {/* Summary Card */}
          <div className="bg-gradient-to-br from-blue-50 to-indigo-50 rounded-lg p-6 border border-blue-200">
            <h3 className="text-lg font-semibold text-gray-900 mb-4">Total Commission Summary</h3>
            <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
              <div>
                <p className="text-sm text-gray-600">Total Variable Fees</p>
                <p className="text-2xl font-bold text-blue-600">
                  {formatCurrency(breakdown.total_variable_fees || 0)}
                </p>
              </div>
              <div>
                <p className="text-sm text-gray-600">Commission %</p>
                <p className="text-2xl font-bold text-green-600">
                  {commissionData.commission_percent?.toFixed(2)}%
                </p>
              </div>
              <div>
                <p className="text-sm text-gray-600">Total Revenue</p>
                <p className="text-2xl font-bold text-purple-600">
                  {formatCurrency(commissionData.room_revenue || 0)}
                </p>
              </div>
              <div>
                <p className="text-sm text-gray-600">Bookings</p>
                <p className="text-2xl font-bold text-orange-600">
                  {commissionData.booking_count || 0}
                </p>
              </div>
            </div>
          </div>

          {/* Cleaning Fees Breakdown */}
          <div className="border rounded-lg p-6">
            <h3 className="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
              <span className="bg-green-100 text-green-800 px-3 py-1 rounded-full text-sm">
                Cleaning Fees
              </span>
            </h3>

            <div className="space-y-4">
              {/* Checkout Cleaning */}
              <div className="bg-green-50 rounded-lg p-4">
                <div className="flex items-center justify-between mb-3">
                  <h4 className="font-semibold text-gray-900">OUT後清掃 (Checkout Cleaning)</h4>
                  <span className="text-lg font-bold text-green-700">
                    {formatCurrency(breakdown.total_checkout_cleaning || 0)}
                  </span>
                </div>
                <div className="grid grid-cols-3 gap-4 text-sm">
                  <div>
                    <p className="text-gray-600">Fee per Booking</p>
                    <p className="font-semibold text-gray-900">
                      {formatCurrency(breakdown.checkout_cleaning_fee_per_booking || 0)}
                    </p>
                  </div>
                  <div>
                    <p className="text-gray-600">Number of Bookings</p>
                    <p className="font-semibold text-gray-900">
                      {breakdown.checkout_cleaning_count || 0}
                    </p>
                  </div>
                  <div>
                    <p className="text-gray-600">Calculation</p>
                    <p className="font-semibold text-gray-900">
                      {breakdown.checkout_cleaning_count || 0} × {formatCurrency(breakdown.checkout_cleaning_fee_per_booking || 0)}
                    </p>
                  </div>
                </div>
                <div className="mt-3 p-3 bg-white rounded border border-green-200">
                  <p className="text-xs text-gray-600">
                    <strong>What is Checkout Cleaning?</strong><br/>
                    Cleaning performed after each guest checks out to prepare the room for the next guest.
                    One cleaning per booking.
                  </p>
                </div>
                {breakdown.checkout_cleaning_dates && breakdown.checkout_cleaning_dates.length > 0 && (
                  <div className="mt-3 p-3 bg-green-100 rounded border border-green-300">
                    <p className="text-xs font-semibold text-green-900 mb-1">Check-in dates for checkout cleaning:</p>
                    <p className="text-sm text-green-800 font-mono">
                      {breakdown.checkout_cleaning_dates.join(', ')}
                    </p>
                  </div>
                )}
              </div>

              {/* Stay Cleaning */}
              <div className="bg-blue-50 rounded-lg p-4">
                <div className="flex items-center justify-between mb-3">
                  <h4 className="font-semibold text-gray-900">連泊時ステイ清掃 (Stay Cleaning)</h4>
                  <span className="text-lg font-bold text-blue-700">
                    {formatCurrency(breakdown.total_stay_cleaning || 0)}
                  </span>
                </div>
                <div className="grid grid-cols-3 gap-4 text-sm">
                  <div>
                    <p className="text-gray-600">Fee per Cleaning</p>
                    <p className="font-semibold text-gray-900">
                      {formatCurrency(breakdown.stay_cleaning_fee_per_cleaning || 0)}
                    </p>
                  </div>
                  <div>
                    <p className="text-gray-600">Number of Cleanings</p>
                    <p className="font-semibold text-gray-900">
                      {breakdown.stay_cleaning_count || 0}
                    </p>
                  </div>
                  <div>
                    <p className="text-gray-600">Calculation</p>
                    <p className="font-semibold text-gray-900">
                      {breakdown.stay_cleaning_count || 0} × {formatCurrency(breakdown.stay_cleaning_fee_per_cleaning || 0)}
                    </p>
                  </div>
                </div>
                <div className="mt-3 p-3 bg-white rounded border border-blue-200">
                  <p className="text-xs text-gray-600">
                    <strong>What is Stay Cleaning?</strong><br/>
                    Cleaning performed during multi-night stays BEFORE checkout. We clean every 3 days, but NOT on the checkout day (that&apos;s covered by checkout cleaning).
                    <br/><strong>Formula:</strong> Number of cleanings = floor((nights - 1) ÷ 3)
                    <br/><strong>Examples:</strong>
                    <br/>• 3 nights = 0 stay cleanings (only checkout cleaning on day 3)
                    <br/>• 4 nights = 1 stay cleaning (day 3, checkout on day 4)
                    <br/>• 6 nights = 1 stay cleaning (day 3, checkout on day 6)
                    <br/>• 7 nights = 2 stay cleanings (days 3 and 6, checkout on day 7)
                  </p>
                </div>
                {breakdown.stay_cleaning_dates && breakdown.stay_cleaning_dates.length > 0 && (
                  <div className="mt-3 p-3 bg-blue-100 rounded border border-blue-300">
                    <p className="text-xs font-semibold text-blue-900 mb-1">Dates for stay cleaning (every 3 days):</p>
                    <p className="text-sm text-blue-800 font-mono">
                      {breakdown.stay_cleaning_dates.join(', ')}
                    </p>
                  </div>
                )}
              </div>

              {/* Total Cleaning */}
              <div className="bg-gray-100 rounded-lg p-4 border-2 border-gray-300">
                <div className="flex items-center justify-between">
                  <div>
                    <h4 className="font-semibold text-gray-900">Total All Cleaning</h4>
                    <p className="text-sm text-gray-600">Checkout + Stay Cleaning</p>
                  </div>
                  <span className="text-2xl font-bold text-gray-900">
                    {formatCurrency(breakdown.total_all_cleaning || 0)}
                  </span>
                </div>
                <div className="mt-3 text-sm">
                  <p className="text-gray-600">
                    Average Cleaning per Booking: <span className="font-semibold text-gray-900">
                      {formatCurrency(breakdown.average_cleaning_per_booking || 0)}
                    </span>
                  </p>
                </div>
              </div>
            </div>
          </div>

          {/* Linen Fees */}
          <div className="border rounded-lg p-6">
            <h3 className="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
              <span className="bg-purple-100 text-purple-800 px-3 py-1 rounded-full text-sm">
                Linen Fees
              </span>
            </h3>
            <div className="bg-purple-50 rounded-lg p-4">
              <div className="flex items-center justify-between mb-3">
                <h4 className="font-semibold text-gray-900">リネン費 (Linen Fee)</h4>
                <span className="text-lg font-bold text-purple-700">
                  {formatCurrency(breakdown.total_linen_fee || 0)}
                </span>
              </div>
              <div className="grid grid-cols-3 gap-4 text-sm">
                <div>
                  <p className="text-gray-600">Fee per Person</p>
                  <p className="font-semibold text-gray-900">
                    {formatCurrency(breakdown.linen_fee_per_person || 0)}
                  </p>
                </div>
                <div>
                  <p className="text-gray-600">Total People</p>
                  <p className="font-semibold text-gray-900">
                    {breakdown.total_people || 0}
                  </p>
                </div>
                <div>
                  <p className="text-gray-600">Calculation</p>
                  <p className="font-semibold text-gray-900">
                    {breakdown.total_people || 0} × {formatCurrency(breakdown.linen_fee_per_person || 0)}
                  </p>
                </div>
              </div>
              <div className="mt-3 p-3 bg-white rounded border border-purple-200">
                <p className="text-xs text-gray-600">
                  <strong>What is Linen Fee?</strong><br/>
                  Fee charged per person for bedding, towels, and linens. Calculated from the people count in each booking.
                </p>
              </div>
            </div>
          </div>

          {/* Monthly Fixed Fees */}
          <div className="border rounded-lg p-6 bg-orange-50">
            <h3 className="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
              <span className="bg-orange-100 text-orange-800 px-3 py-1 rounded-full text-sm">
                Monthly Fixed Fees
              </span>
              <span className="text-xs text-orange-700 font-normal">(Not included in per-booking total)</span>
            </h3>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div className="bg-white rounded-lg p-4 border border-orange-200">
                <p className="text-sm text-gray-600 mb-1">OP業務委託料 (Operation Management Fee)</p>
                <p className="text-xl font-bold text-orange-700">
                  {formatCurrency(breakdown.operation_management_fee_monthly || 0)}
                  <span className="text-sm font-normal text-gray-600">/month</span>
                </p>
              </div>
              <div className="bg-white rounded-lg p-4 border border-orange-200">
                <p className="text-sm text-gray-600 mb-1">月1回定期点検 (Monthly Inspection)</p>
                <p className="text-xl font-bold text-orange-700">
                  {formatCurrency(breakdown.monthly_inspection_fee || 0)}
                  <span className="text-sm font-normal text-gray-600">/month</span>
                </p>
              </div>
              <div className="bg-white rounded-lg p-4 border border-orange-200">
                <p className="text-sm text-gray-600 mb-1">駆け付け要員（固定） (Emergency Response Staff)</p>
                <p className="text-xl font-bold text-orange-700">
                  {formatCurrency(breakdown.emergency_staff_fee_monthly || 0)}
                  <span className="text-sm font-normal text-gray-600">/month</span>
                </p>
              </div>
              <div className="bg-white rounded-lg p-4 border border-orange-200">
                <p className="text-sm text-gray-600 mb-1">ゴミ回収費用 (Garbage Collection Fee)</p>
                <p className="text-xl font-bold text-orange-700">
                  {formatCurrency(breakdown.garbage_collection_fee_monthly || 0)}
                  <span className="text-sm font-normal text-gray-600">/month</span>
                </p>
              </div>
            </div>
            <div className="mt-3 p-3 bg-white rounded border border-orange-300">
              <p className="text-xs text-orange-800">
                <strong>Note:</strong> These monthly fees are charged once per month regardless of booking count.
                Add these to the total variable fees when calculating the complete monthly commission.
              </p>
            </div>
          </div>

          {/* Calculation Formula */}
          <div className="border-2 border-blue-300 rounded-lg p-6 bg-blue-50">
            <h3 className="text-lg font-semibold text-gray-900 mb-4">📊 How We Calculate</h3>
            <div className="space-y-3 text-sm">
              <div className="bg-white rounded p-3">
                <p className="font-semibold text-gray-900 mb-2">Per-Booking Variable Fees:</p>
                <div className="font-mono text-xs space-y-1 text-gray-700">
                  <p>Total = Checkout Cleaning + Stay Cleaning + Linen Fees</p>
                  <p>Total = ({breakdown.checkout_cleaning_count} × {formatCurrency(breakdown.checkout_cleaning_fee_per_booking || 0)}) +
                     ({breakdown.stay_cleaning_count} × {formatCurrency(breakdown.stay_cleaning_fee_per_cleaning || 0)}) +
                     ({breakdown.total_people} × {formatCurrency(breakdown.linen_fee_per_person || 0)})</p>
                  <p className="text-blue-700 font-bold">Total = {formatCurrency(breakdown.total_variable_fees || 0)}</p>
                </div>
              </div>

              <div className="bg-white rounded p-3">
                <p className="font-semibold text-gray-900 mb-2">Commission Percentage (for comparison):</p>
                <div className="font-mono text-xs space-y-1 text-gray-700">
                  <p>Commission % = (Total Variable Fees ÷ Room Revenue) × 100</p>
                  <p>Commission % = ({formatCurrency(breakdown.total_variable_fees || 0)} ÷ {formatCurrency(commissionData.room_revenue || 0)}) × 100</p>
                  <p className="text-green-700 font-bold">Commission % = {commissionData.commission_percent?.toFixed(2)}%</p>
                </div>
              </div>

              <div className="bg-white rounded p-3">
                <p className="font-semibold text-gray-900 mb-2">Average Cleaning per Booking:</p>
                <div className="font-mono text-xs space-y-1 text-gray-700">
                  <p>Avg Cleaning = Total Cleaning ÷ Booking Count</p>
                  <p>Avg Cleaning = {formatCurrency(breakdown.total_all_cleaning || 0)} ÷ {breakdown.checkout_cleaning_count}</p>
                  <p className="text-purple-700 font-bold">Avg Cleaning = {formatCurrency(breakdown.average_cleaning_per_booking || 0)}</p>
                </div>
              </div>
            </div>
          </div>

          {/* Complete Monthly Total */}
          <div className="bg-gradient-to-br from-indigo-100 to-purple-100 rounded-lg p-6 border-2 border-indigo-300">
            <h3 className="text-xl font-bold text-gray-900 mb-4">💰 Complete Monthly Commission</h3>
            <div className="space-y-2 text-sm">
              <div className="flex justify-between">
                <span className="text-gray-700">Variable Fees (Bookings):</span>
                <span className="font-semibold">{formatCurrency(breakdown.total_variable_fees || 0)}</span>
              </div>
              <div className="flex justify-between">
                <span className="text-gray-700">Operation Management Fee:</span>
                <span className="font-semibold">{formatCurrency(breakdown.operation_management_fee_monthly || 0)}</span>
              </div>
              <div className="flex justify-between">
                <span className="text-gray-700">Monthly Inspection Fee:</span>
                <span className="font-semibold">{formatCurrency(breakdown.monthly_inspection_fee || 0)}</span>
              </div>
              <div className="flex justify-between">
                <span className="text-gray-700">Emergency Staff Fee:</span>
                <span className="font-semibold">{formatCurrency(breakdown.emergency_staff_fee_monthly || 0)}</span>
              </div>
              <div className="flex justify-between border-b-2 border-indigo-300 pb-2">
                <span className="text-gray-700">Garbage Collection Fee:</span>
                <span className="font-semibold">{formatCurrency(breakdown.garbage_collection_fee_monthly || 0)}</span>
              </div>
              <div className="flex justify-between pt-2">
                <span className="text-lg font-bold text-gray-900">Total Monthly Commission:</span>
                <span className="text-2xl font-bold text-indigo-700">
                  {formatCurrency(
                    (breakdown.total_variable_fees || 0) +
                    (breakdown.operation_management_fee_monthly || 0) +
                    (breakdown.monthly_inspection_fee || 0) +
                    (breakdown.emergency_staff_fee_monthly || 0) +
                    (breakdown.garbage_collection_fee_monthly || 0)
                  )}
                </span>
              </div>
            </div>
          </div>
            </>
          )}
        </div>

        {/* Footer */}
        <div className="sticky bottom-0 bg-gray-50 border-t px-6 py-4">
          <button
            onClick={onClose}
            className="w-full bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition-colors font-semibold"
          >
            Close Breakdown
          </button>
        </div>
      </div>
    </div>
  );
}
