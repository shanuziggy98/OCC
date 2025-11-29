'use client';

import React, { useState, useEffect } from 'react';
import { LineChart, Line, XAxis, YAxis, CartesianGrid, Tooltip, Legend, ResponsiveContainer } from 'recharts';
import { TrendingUp, TrendingDown, DollarSign, ArrowLeft } from 'lucide-react';

interface RoomMetrics {
  month: number;
  monthName: string;
  occ_rate: number;
  adr: number;
  revpar: number;
  revenue: number;
  avg_lead_time: number;
}

interface RoomAnalyticsProps {
  propertyName: string;
  roomType: string;
  onBack: () => void;
  currentYear?: number;
}

export default function RoomAnalytics({ propertyName, roomType, onBack, currentYear = new Date().getFullYear() }: RoomAnalyticsProps) {
  const [currentYearData, setCurrentYearData] = useState<RoomMetrics[]>([]);
  const [lastYearData, setLastYearData] = useState<RoomMetrics[]>([]);
  const [loading, setLoading] = useState(true);
  const [selectedMetric, setSelectedMetric] = useState<'occ_rate' | 'adr' | 'revpar' | 'revenue' | 'avg_lead_time'>('occ_rate');

  const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('ja-JP', { style: 'currency', currency: 'JPY', maximumFractionDigits: 0 }).format(amount);
  };

  const formatPercent = (rate: number) => {
    return `${rate.toFixed(2)}%`;
  };

  useEffect(() => {
    fetchRoomData();
  }, [propertyName, roomType, currentYear]);

  const fetchRoomData = async () => {
    setLoading(true);
    try {
      const phpApiUrl = process.env.NEXT_PUBLIC_PHP_API_URL || 'https://exseed.main.jp/WG/analysis/OCC/occupancy_metrics_api.php';

      // Fetch current year room data
      const currentYearMonthly: RoomMetrics[] = [];
      for (let month = 1; month <= 12; month++) {
        const response = await fetch(`${phpApiUrl}?action=${propertyName.toLowerCase()}_rooms&year=${currentYear}&month=${month}`);
        const data = await response.json();

        if (!data.error && data.rooms) {
          const room = data.rooms.find((r: { room_type: string }) => r.room_type === roomType);
          if (room) {
            currentYearMonthly.push({
              month,
              monthName: `${month}月`,
              occ_rate: room.occ_rate,
              adr: room.adr,
              revpar: room.revpar,
              revenue: room.room_revenue,
              avg_lead_time: room.avg_lead_time || 0
            });
          }
        }
      }

      // Fetch last year room data
      const lastYearMonthly: RoomMetrics[] = [];
      for (let month = 1; month <= 12; month++) {
        const response = await fetch(`${phpApiUrl}?action=${propertyName.toLowerCase()}_rooms&year=${currentYear - 1}&month=${month}`);
        const data = await response.json();

        if (!data.error && data.rooms) {
          const room = data.rooms.find((r: { room_type: string }) => r.room_type === roomType);
          if (room) {
            lastYearMonthly.push({
              month,
              monthName: `${month}月`,
              occ_rate: room.occ_rate,
              adr: room.adr,
              revpar: room.revpar,
              revenue: room.room_revenue,
              avg_lead_time: room.avg_lead_time || 0
            });
          }
        }
      }

      setCurrentYearData(currentYearMonthly);
      setLastYearData(lastYearMonthly);
    } catch (error) {
      console.error('Error fetching room analytics:', error);
    } finally {
      setLoading(false);
    }
  };

  const getComparisonData = () => {
    return currentYearData.map((current, index) => ({
      month: current.monthName,
      [`${currentYear}`]: current[selectedMetric],
      [`${currentYear - 1}`]: lastYearData[index]?.[selectedMetric] || 0,
    }));
  };

  const calculateYoYChange = () => {
    if (!currentYearData.length || !lastYearData.length) return { change: 0, isPositive: false };

    const currentAvg = currentYearData.reduce((sum, d) => sum + d[selectedMetric], 0) / currentYearData.length;
    const lastYearAvg = lastYearData.reduce((sum, d) => sum + d[selectedMetric], 0) / lastYearData.length;

    const change = ((currentAvg - lastYearAvg) / lastYearAvg) * 100;
    return { change: Math.abs(change), isPositive: change > 0 };
  };

  const getPriceRecommendation = () => {
    if (!currentYearData.length) return null;

    const avgOcc = currentYearData.reduce((sum, d) => sum + d.occ_rate, 0) / currentYearData.length;
    const avgADR = currentYearData.reduce((sum, d) => sum + d.adr, 0) / currentYearData.length;

    if (avgOcc >= 80) {
      return {
        recommendation: 'increase',
        percent: 10,
        reason: `High occupancy (${avgOcc.toFixed(1)}%) allows for price increase`,
        newADR: avgADR * 1.1
      };
    } else if (avgOcc < 50) {
      return {
        recommendation: 'decrease',
        percent: 10,
        reason: `Low occupancy (${avgOcc.toFixed(1)}%) suggests price reduction`,
        newADR: avgADR * 0.9
      };
    }

    return {
      recommendation: 'maintain',
      percent: 0,
      reason: `Moderate occupancy (${avgOcc.toFixed(1)}%) - maintain current pricing`,
      newADR: avgADR
    };
  };

  const yoyChange = calculateYoYChange();
  const priceRec = getPriceRecommendation();

  if (loading) {
    return (
      <div className="flex items-center justify-center h-96">
        <div className="text-gray-600">Loading room analytics...</div>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <div className="flex items-center gap-4">
        <button
          onClick={onBack}
          className="flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors"
        >
          <ArrowLeft className="h-4 w-4" />
          Back
        </button>
        <h3 className="text-2xl font-bold text-gray-900">{propertyName} - {roomType} Analytics</h3>
      </div>

      {/* YoY Performance */}
      <div className="bg-white rounded-lg shadow p-6">
        <div className="flex items-center justify-between mb-4">
          <h4 className="text-lg font-semibold text-gray-900">Year-over-Year Comparison</h4>
          <div className={`flex items-center gap-2 px-4 py-2 rounded-lg ${yoyChange.isPositive ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'}`}>
            {yoyChange.isPositive ? <TrendingUp className="h-5 w-5" /> : <TrendingDown className="h-5 w-5" />}
            <span className="font-bold">{yoyChange.change.toFixed(1)}%</span>
          </div>
        </div>

        {/* Metric Selector */}
        <div className="mb-6 flex gap-2 flex-wrap">
          {(['occ_rate', 'adr', 'revpar', 'revenue', 'avg_lead_time'] as const).map((metric) => (
            <button
              key={metric}
              onClick={() => setSelectedMetric(metric)}
              className={`px-4 py-2 rounded-lg font-medium transition-colors ${
                selectedMetric === metric ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
              }`}
            >
              {metric === 'occ_rate' ? 'OCC' :
               metric === 'adr' ? 'ADR' :
               metric === 'revpar' ? 'RevPAR' :
               metric === 'revenue' ? 'Revenue' :
               'Lead Time'}
            </button>
          ))}
        </div>

        <ResponsiveContainer width="100%" height={300}>
          <LineChart data={getComparisonData()}>
            <CartesianGrid strokeDasharray="3 3" />
            <XAxis dataKey="month" />
            <YAxis />
            <Tooltip />
            <Legend />
            <Line type="monotone" dataKey={`${currentYear}`} stroke="#3b82f6" strokeWidth={2} />
            <Line type="monotone" dataKey={`${currentYear - 1}`} stroke="#9ca3af" strokeWidth={2} strokeDasharray="5 5" />
          </LineChart>
        </ResponsiveContainer>
      </div>

      {/* Price Recommendation */}
      {priceRec && (
        <div className="bg-white rounded-lg shadow p-6">
          <div className="flex items-center gap-2 mb-4">
            <DollarSign className="h-6 w-6 text-blue-600" />
            <h4 className="text-lg font-semibold text-gray-900">Pricing Recommendation</h4>
          </div>

          <div className={`p-4 rounded-lg ${
            priceRec.recommendation === 'increase' ? 'bg-green-50 border-2 border-green-200' :
            priceRec.recommendation === 'decrease' ? 'bg-red-50 border-2 border-red-200' :
            'bg-gray-50 border-2 border-gray-200'
          }`}>
            <div className="flex items-center justify-between mb-3">
              <span className="text-lg font-medium text-gray-900">{priceRec.reason}</span>
              <div className={`flex items-center gap-2 px-3 py-1 rounded-full text-sm font-medium ${
                priceRec.recommendation === 'increase' ? 'bg-green-100 text-green-700' :
                priceRec.recommendation === 'decrease' ? 'bg-red-100 text-red-700' :
                'bg-gray-100 text-gray-700'
              }`}>
                {priceRec.recommendation === 'increase' && <TrendingUp className="h-4 w-4" />}
                {priceRec.recommendation === 'decrease' && <TrendingDown className="h-4 w-4" />}
                {priceRec.percent > 0 ? `${priceRec.percent}%` : 'Maintain'}
              </div>
            </div>

            <div className="grid grid-cols-2 gap-4">
              <div>
                <p className="text-sm text-gray-600 mb-1">Current Avg ADR</p>
                <p className="text-xl font-bold text-gray-900">
                  {formatCurrency(currentYearData.reduce((sum, d) => sum + d.adr, 0) / currentYearData.length)}
                </p>
              </div>
              <div>
                <p className="text-sm text-gray-600 mb-1">Recommended ADR</p>
                <p className="text-xl font-bold text-blue-600">{formatCurrency(priceRec.newADR)}</p>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Monthly Details */}
      <div className="bg-white rounded-lg shadow p-6">
        <h4 className="text-lg font-semibold text-gray-900 mb-4">Monthly Performance</h4>
        <div className="overflow-x-auto">
          <table className="min-w-full divide-y divide-gray-200">
            <thead className="bg-gray-50">
              <tr>
                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Month</th>
                <th className="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">OCC {currentYear}</th>
                <th className="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">OCC {currentYear - 1}</th>
                <th className="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">ADR {currentYear}</th>
                <th className="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Revenue {currentYear}</th>
              </tr>
            </thead>
            <tbody className="bg-white divide-y divide-gray-200">
              {currentYearData.map((current, index) => {
                const lastYear = lastYearData[index];
                return (
                  <tr key={index} className="hover:bg-gray-50">
                    <td className="px-4 py-3 font-medium text-gray-900">{current.monthName}</td>
                    <td className="px-4 py-3 text-right text-gray-900">{formatPercent(current.occ_rate)}</td>
                    <td className="px-4 py-3 text-right text-gray-600">{lastYear ? formatPercent(lastYear.occ_rate) : '-'}</td>
                    <td className="px-4 py-3 text-right text-gray-900">{formatCurrency(current.adr)}</td>
                    <td className="px-4 py-3 text-right text-gray-900">{formatCurrency(current.revenue)}</td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
}
