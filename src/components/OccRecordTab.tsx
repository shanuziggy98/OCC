'use client';

import React, { useState, useEffect } from 'react';

interface DailyOccupancyData {
  property_name: string;
  total_rooms: number;
  daily_occupancy: { [date: string]: number };
}

interface RevenueSummary {
  total_sales_today: number;
  total_sales_yesterday: number;
  difference: number;
  today_date: string;
  yesterday_date: string;
  year_start: string;
}

interface OccRecordResponse {
  start_date: string;
  end_date: string;
  dates: string[];
  properties: DailyOccupancyData[];
  daily_ytd_revenue?: { [date: string]: number }; // Total Sales Number (cumulative)
  daily_differences?: { [date: string]: number }; // Daily sales difference
}

export default function OccRecordTab() {
  const [data, setData] = useState<OccRecordResponse | null>(null);
  const [loading, setLoading] = useState(true);
  const [startDate, setStartDate] = useState(() => {
    // Default to first day of current month
    const now = new Date();
    return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-01`;
  });
  const [endDate, setEndDate] = useState(() => {
    // Default to today
    const now = new Date();
    return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
  });

  const fetchData = async () => {
    setLoading(true);
    try {
      const response = await fetch(
        `https://exseed.main.jp/WG/analysis/OCC/occupancy_metrics_api.php?action=daily_occupancy&start_date=${startDate}&end_date=${endDate}`
      );
      const result = await response.json();
      console.log('OCC Record API Response:', result);
      setData(result);
    } catch (error) {
      console.error('Error fetching daily occupancy data:', error);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchData();
    //Auto-refresh every hour
    const interval = setInterval(fetchData, 3600000);
    return () => clearInterval(interval);
  }, [startDate, endDate]);

  if (loading) {
    return (
      <div className="flex items-center justify-center p-8">
        <div className="text-xl text-gray-400">Loading...</div>
      </div>
    );
  }

  if (!data || !data.properties || data.properties.length === 0) {
    return (
      <div className="p-8">
        <h2 className="text-2xl font-bold mb-4 text-gray-700">OCC Record - Daily Occupancy Rates</h2>

        {/* Date Range Selector */}
        <div className="flex gap-4 items-center mb-4">
          <div>
            <label className="block text-sm font-medium mb-1 text-gray-500">Start Date</label>
            <input
              type="date"
              value={startDate}
              onChange={(e) => setStartDate(e.target.value)}
              className="border rounded px-3 py-2 text-gray-700"
            />
          </div>
          <div>
            <label className="block text-sm font-medium mb-1 text-gray-500">End Date</label>
            <input
              type="date"
              value={endDate}
              onChange={(e) => setEndDate(e.target.value)}
              className="border rounded px-3 py-2 text-gray-700"
            />
          </div>
          <button
            onClick={fetchData}
            className="mt-6 bg-blue-400 text-white px-6 py-2 rounded hover:bg-blue-500"
          >
            Update
          </button>
        </div>

        <div className="bg-yellow-50 border border-yellow-200 rounded-lg p-6 text-center">
          <p className="text-lg font-semibold mb-2 text-gray-600">No occupancy data available</p>
          <p className="text-sm text-gray-500 mb-4">
            Date range: {startDate} to {endDate}
          </p>
          <div className="text-left bg-white p-4 rounded border">
            <p className="font-semibold mb-2 text-gray-600">Possible reasons:</p>
            <ol className="list-decimal ml-6 space-y-1 text-sm text-gray-500">
              <li>The database table &apos;daily_occupancy_records&apos; has not been created yet</li>
              <li>No data has been saved for this date range</li>
              <li>The 8 AM daily trigger has not run yet</li>
            </ol>
            <p className="mt-4 text-sm font-semibold text-gray-600">To fix this:</p>
            <ol className="list-decimal ml-6 space-y-1 text-sm text-gray-500">
              <li>Run the SQL file: create_daily_occupancy_table.sql</li>
              <li>Trigger the save manually:
                <a
                  href="https://exseed.main.jp/WG/analysis/OCC/save_daily_occupancy.php?auth_key=exseed_daily_occ_2025&days=30"
                  target="_blank"
                  rel="noopener noreferrer"
                  className="text-blue-500 hover:underline block mt-1"
                >
                  https://exseed.main.jp/WG/analysis/OCC/save_daily_occupancy.php?auth_key=exseed_daily_occ_2025&days=30
                </a>
              </li>
              <li>Setup Google Apps Script trigger (see DAILY_OCC_SETUP.md)</li>
            </ol>
          </div>
        </div>
      </div>
    );
  }

  const formatDate = (dateStr: string) => {
    const date = new Date(dateStr);
    return `${date.getMonth() + 1}/${date.getDate()}`;
  };

  const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('ja-JP', {
      style: 'currency',
      currency: 'JPY',
      maximumFractionDigits: 0
    }).format(amount);
  };

  return (
    <div className="p-4">
      <div className="mb-6">
        <h2 className="text-2xl font-bold mb-4 text-gray-700">OCC Record - Daily Occupancy Rates</h2>

        {/* Date Range Selector */}
        <div className="flex gap-4 items-center mb-4">
          <div>
            <label className="block text-sm font-medium mb-1 text-gray-500">Start Date</label>
            <input
              type="date"
              value={startDate}
              onChange={(e) => setStartDate(e.target.value)}
              className="border rounded px-3 py-2 text-gray-700"
            />
          </div>
          <div>
            <label className="block text-sm font-medium mb-1 text-gray-500">End Date</label>
            <input
              type="date"
              value={endDate}
              onChange={(e) => setEndDate(e.target.value)}
              className="border rounded px-3 py-2 text-gray-700"
            />
          </div>
          <button
            onClick={fetchData}
            className="mt-6 bg-blue-400 text-white px-6 py-2 rounded hover:bg-blue-500"
          >
            Update
          </button>
        </div>

        <p className="text-sm text-gray-500">
          Last updated: {new Date().toLocaleString('ja-JP', { timeZone: 'Asia/Tokyo' })}
        </p>
      </div>

      {/* Occupancy Table */}
      <div className="overflow-x-auto">
        <table className="min-w-full border-collapse border border-gray-300">
          <thead>
            <tr className="bg-gray-100">
              <th className="border border-gray-300 px-4 py-2 text-left sticky left-0 bg-gray-100 z-10 text-gray-600">
                Property
              </th>
              {data.dates.map((date) => (
                <th key={date} className="border border-gray-300 px-4 py-2 text-center whitespace-nowrap text-gray-600">
                  {formatDate(date)}
                  <br />
                  <span className="text-xs text-gray-400">{date}</span>
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {/* Total Sales Number Row */}
            {data.daily_ytd_revenue && (
              <tr className="bg-blue-50 font-semibold">
                <td className="border border-gray-300 px-4 py-2 sticky left-0 bg-blue-50 z-10 text-gray-700">
                  Total Sales Number
                </td>
                {data.dates.map((date) => {
                  const ytdRevenue = data.daily_ytd_revenue?.[date] || 0;
                  return (
                    <td
                      key={date}
                      className="border border-gray-300 px-2 py-2 text-center text-xs text-gray-700"
                    >
                      {formatCurrency(ytdRevenue)}
                    </td>
                  );
                })}
              </tr>
            )}

            {/* Total Sales Difference Row */}
            {data.daily_differences && (
              <tr className="bg-green-50 font-semibold">
                <td className="border border-gray-300 px-4 py-2 sticky left-0 bg-green-50 z-10 text-gray-700">
                  Total Sales Difference
                </td>
                {data.dates.map((date) => {
                  const difference = data.daily_differences?.[date] || 0;
                  const isPositive = difference >= 0;
                  const isFirst = difference === 0 && date === data.dates[0];

                  return (
                    <td
                      key={date}
                      className={`border border-gray-300 px-2 py-2 text-center text-xs ${
                        isFirst
                          ? 'text-gray-400'
                          : isPositive
                          ? 'text-green-700'
                          : 'text-red-700'
                      }`}
                    >
                      {isFirst
                        ? '-'
                        : `${isPositive ? '+' : ''}${formatCurrency(difference)}`}
                    </td>
                  );
                })}
              </tr>
            )}

            {/* Property Rows */}
            {data.properties.map((property) => (
              <tr key={property.property_name} className="hover:bg-gray-50">
                <td className="border border-gray-300 px-4 py-2 font-medium sticky left-0 bg-white z-10 text-gray-600">
                  {property.property_name}
                </td>
                {data.dates.map((date) => {
                  const rate = property.daily_occupancy[date] || 0;
                  const bgColor =
                    rate === 0 ? 'bg-white' :
                    rate < 25 ? 'bg-red-100' :
                    rate < 50 ? 'bg-yellow-100' :
                    rate < 75 ? 'bg-green-100' :
                    'bg-green-200';

                  return (
                    <td
                      key={date}
                      className={`border border-gray-300 px-4 py-2 text-center ${bgColor} text-gray-600`}
                    >
                      {rate.toFixed(2)}%
                    </td>
                  );
                })}
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}
