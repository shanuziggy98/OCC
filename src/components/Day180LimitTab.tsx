'use client';

import React, { useState, useEffect } from 'react';
import { Calendar, AlertCircle, CheckCircle, AlertTriangle, RefreshCw, TrendingDown } from 'lucide-react';

interface Property180Data {
  property_name: string;
  total_rooms: number;
  limit_days: number;
  booked_days: number;
  remaining_days: number;
  utilization_percent: number;
  is_over_limit: boolean;
  booking_count: number;
  status: 'safe' | 'warning' | 'critical';
}

interface FiscalYearData {
  current_fiscal_year: string;
  fiscal_year_start: string;
  fiscal_year_end: string;
  properties: Property180Data[];
  message?: string;
}

export default function Day180LimitTab() {
  const [data, setData] = useState<FiscalYearData | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const fetchData = async () => {
    setLoading(true);
    setError(null);
    try {
      const phpApiUrl = process.env.NEXT_PUBLIC_PHP_API_URL || 'https://exseed.main.jp/WG/analysis/OCC/occupancy_metrics_api.php';
      const response = await fetch(`${phpApiUrl}?action=180_day_limit`);
      const result = await response.json();

      if (result.error) {
        setError(result.error);
      } else {
        setData(result);
      }
    } catch (err) {
      console.error('Error fetching 180-day limit data:', err);
      setError('Failed to load 180-day limit data');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchData();
  }, []);

  const getStatusIcon = (status: string) => {
    switch (status) {
      case 'safe':
        return <CheckCircle className="h-5 w-5 text-green-600" />;
      case 'warning':
        return <AlertTriangle className="h-5 w-5 text-yellow-600" />;
      case 'critical':
        return <AlertCircle className="h-5 w-5 text-red-600" />;
      default:
        return null;
    }
  };

  const getStatusColor = (status: string) => {
    switch (status) {
      case 'safe':
        return 'bg-green-50 border-green-200';
      case 'warning':
        return 'bg-yellow-50 border-yellow-200';
      case 'critical':
        return 'bg-red-50 border-red-200';
      default:
        return 'bg-gray-50 border-gray-200';
    }
  };

  const getProgressBarColor = (status: string) => {
    switch (status) {
      case 'safe':
        return 'bg-green-500';
      case 'warning':
        return 'bg-yellow-500';
      case 'critical':
        return 'bg-red-500';
      default:
        return 'bg-gray-500';
    }
  };

  if (loading) {
    return (
      <div className="flex items-center justify-center py-12">
        <RefreshCw className="h-8 w-8 animate-spin text-blue-600" />
        <span className="ml-3 text-gray-600">Loading 180-day limit data...</span>
      </div>
    );
  }

  if (error) {
    return (
      <div className="bg-red-50 border border-red-200 rounded-lg p-6">
        <div className="flex items-center gap-2 mb-2">
          <AlertCircle className="h-5 w-5 text-red-600" />
          <span className="font-medium text-red-800">Error</span>
        </div>
        <p className="text-sm text-red-700">{error}</p>
      </div>
    );
  }

  if (!data || data.properties.length === 0) {
    return (
      <div className="bg-blue-50 border border-blue-200 rounded-lg p-6">
        <div className="flex items-center gap-2 mb-2">
          <AlertCircle className="h-5 w-5 text-blue-600" />
          <span className="font-medium text-blue-800">No Data</span>
        </div>
        <p className="text-sm text-blue-700">
          {data?.message || 'No properties with 180-day limit found'}
        </p>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      {/* Header Info */}
      <div className="bg-white rounded-lg shadow-sm border p-6">
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-3">
            <div className="bg-blue-600 p-2 rounded-lg">
              <Calendar className="h-6 w-6 text-white" />
            </div>
            <div>
              <h2 className="text-xl font-bold text-gray-900">180-Day Booking Limit Tracking</h2>
              <p className="text-sm text-gray-600">
                Japanese Fiscal Year: <span className="font-medium">{data.current_fiscal_year}</span>
                {' '}({data.fiscal_year_start} to {data.fiscal_year_end})
              </p>
            </div>
          </div>
          <button
            onClick={fetchData}
            className="flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700"
          >
            <RefreshCw className="h-4 w-4" />
            Refresh
          </button>
        </div>
      </div>

      {/* Summary Cards */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div className="bg-white rounded-lg shadow-sm border p-4">
          <div className="text-sm text-gray-600 mb-1">Total Properties</div>
          <div className="text-2xl font-bold text-gray-900">{data.properties.length}</div>
        </div>
        <div className="bg-white rounded-lg shadow-sm border p-4">
          <div className="text-sm text-gray-600 mb-1">Properties at Risk</div>
          <div className="text-2xl font-bold text-red-600">
            {data.properties.filter(p => p.status === 'critical' || p.is_over_limit).length}
          </div>
        </div>
        <div className="bg-white rounded-lg shadow-sm border p-4">
          <div className="text-sm text-gray-600 mb-1">Properties with Warning</div>
          <div className="text-2xl font-bold text-yellow-600">
            {data.properties.filter(p => p.status === 'warning').length}
          </div>
        </div>
      </div>

      {/* Property Details */}
      <div className="bg-white rounded-lg shadow-sm border overflow-hidden">
        <div className="p-4 bg-gray-50 border-b">
          <h3 className="text-lg font-semibold text-gray-900">Property Status</h3>
        </div>
        <div className="overflow-x-auto">
          <table className="w-full">
            <thead className="bg-gray-50 border-b">
              <tr>
                <th className="px-4 py-3 text-left font-medium text-gray-900">Status</th>
                <th className="px-4 py-3 text-left font-medium text-gray-900">Property</th>
                <th className="px-4 py-3 text-right font-medium text-gray-900">Limit</th>
                <th className="px-4 py-3 text-right font-medium text-gray-900">Booked</th>
                <th className="px-4 py-3 text-right font-medium text-gray-900">Remaining</th>
                <th className="px-4 py-3 text-right font-medium text-gray-900">Utilization</th>
                <th className="px-4 py-3 text-left font-medium text-gray-900">Progress</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-200">
              {data.properties.map((property, index) => (
                <tr
                  key={index}
                  className={`hover:bg-gray-50 ${getStatusColor(property.status)}`}
                >
                  <td className="px-4 py-3">
                    <div className="flex items-center gap-2">
                      {getStatusIcon(property.status)}
                      {property.is_over_limit && (
                        <span className="text-xs font-medium text-red-600 bg-red-100 px-2 py-1 rounded">
                          OVER LIMIT
                        </span>
                      )}
                    </div>
                  </td>
                  <td className="px-4 py-3 font-medium text-gray-900">{property.property_name}</td>
                  <td className="px-4 py-3 text-right text-gray-900">{property.limit_days} days</td>
                  <td className="px-4 py-3 text-right">
                    <span className={property.is_over_limit ? 'font-bold text-red-600' : 'text-gray-900'}>
                      {property.booked_days} days
                    </span>
                  </td>
                  <td className="px-4 py-3 text-right">
                    <span
                      className={`font-medium ${
                        property.remaining_days <= 0
                          ? 'text-red-600'
                          : property.remaining_days <= 30
                          ? 'text-orange-600'
                          : property.remaining_days <= 60
                          ? 'text-yellow-600'
                          : 'text-green-600'
                      }`}
                    >
                      {property.remaining_days} days
                    </span>
                  </td>
                  <td className="px-4 py-3 text-right">
                    <span
                      className={`font-medium ${
                        property.utilization_percent >= 100
                          ? 'text-red-600'
                          : property.utilization_percent >= 83
                          ? 'text-orange-600'
                          : property.utilization_percent >= 67
                          ? 'text-yellow-600'
                          : 'text-green-600'
                      }`}
                    >
                      {property.utilization_percent.toFixed(1)}%
                    </span>
                  </td>
                  <td className="px-4 py-3">
                    <div className="w-full">
                      <div className="w-full bg-gray-200 rounded-full h-2.5">
                        <div
                          className={`h-2.5 rounded-full ${getProgressBarColor(property.status)}`}
                          style={{ width: `${Math.min(100, property.utilization_percent)}%` }}
                        ></div>
                      </div>
                      <div className="flex justify-between text-xs text-gray-600 mt-1">
                        <span>0</span>
                        <span>180</span>
                      </div>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>

      {/* Legend */}
      <div className="bg-white rounded-lg shadow-sm border p-4">
        <h4 className="text-sm font-semibold text-gray-900 mb-3">Status Legend</h4>
        <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
          <div className="flex items-center gap-2">
            <CheckCircle className="h-4 w-4 text-green-600" />
            <span className="text-sm text-gray-700">
              <strong>Safe:</strong> More than 60 days remaining
            </span>
          </div>
          <div className="flex items-center gap-2">
            <AlertTriangle className="h-4 w-4 text-yellow-600" />
            <span className="text-sm text-gray-700">
              <strong>Warning:</strong> 30-60 days remaining
            </span>
          </div>
          <div className="flex items-center gap-2">
            <AlertCircle className="h-4 w-4 text-red-600" />
            <span className="text-sm text-gray-700">
              <strong>Critical:</strong> Less than 30 days remaining
            </span>
          </div>
        </div>
      </div>

      {/* Info Box */}
      <div className="bg-blue-50 border border-blue-200 rounded-lg p-4">
        <div className="flex items-start gap-2">
          <TrendingDown className="h-5 w-5 text-blue-600 mt-0.5" />
          <div>
            <h4 className="text-sm font-semibold text-blue-900 mb-1">About the 180-Day Limit</h4>
            <p className="text-sm text-blue-700">
              According to Japanese law, certain properties can only accept bookings for a maximum of 180 days
              per fiscal year (April 1 - March 31). This tracker helps you monitor how many days you have left
              to accept reservations for properties with this restriction.
            </p>
          </div>
        </div>
      </div>
    </div>
  );
}
