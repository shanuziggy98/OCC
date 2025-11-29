'use client';

import React, { useState, useEffect } from 'react';
import { LineChart, Line, BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, Legend, ResponsiveContainer } from 'recharts';
import { TrendingUp, TrendingDown, AlertCircle, DollarSign, Calendar, ArrowLeft } from 'lucide-react';

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
}

interface MonthlyData {
  month: number;
  monthName: string;
  occ_rate: number;
  adr: number;
  revpar: number;
  revenue: number;
  bookings: number;
  avg_lead_time: number;
}

interface PriceRecommendation {
  currentADR: number;
  recommendedADR: number;
  change: number;
  changePercent: number;
  reason: string;
  indicator: 'increase' | 'decrease' | 'maintain';
}

interface PropertyAnalyticsProps {
  propertyName: string;
  onBack: () => void;
  currentYear?: number;
}

export default function PropertyAnalytics({ propertyName, onBack, currentYear = new Date().getFullYear() }: PropertyAnalyticsProps) {
  const [currentYearData, setCurrentYearData] = useState<MonthlyData[]>([]);
  const [lastYearData, setLastYearData] = useState<MonthlyData[]>([]);
  const [loading, setLoading] = useState(true);
  const [priceRecommendations, setPriceRecommendations] = useState<PriceRecommendation[]>([]);
  const [selectedMetric, setSelectedMetric] = useState<'occ_rate' | 'adr' | 'revpar' | 'revenue' | 'avg_lead_time'>('occ_rate');

  const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('ja-JP', { style: 'currency', currency: 'JPY', maximumFractionDigits: 0 }).format(amount);
  };

  const formatPercent = (rate: number) => {
    return `${rate.toFixed(2)}%`;
  };

  useEffect(() => {
    fetchAnalyticsData();
  }, [propertyName, currentYear]);

  const fetchAnalyticsData = async () => {
    setLoading(true);
    try {
      const phpApiUrl = process.env.NEXT_PUBLIC_PHP_API_URL || 'https://exseed.main.jp/WG/analysis/OCC/occupancy_metrics_api.php';

      // Fetch current year data
      const currentYearMonthly: MonthlyData[] = [];
      for (let month = 1; month <= 12; month++) {
        const response = await fetch(`${phpApiUrl}?year=${currentYear}&month=${month}`);
        const data = await response.json();

        if (!data.error && data.properties) {
          const property = data.properties.find((p: PropertyMetrics) => p.property_name === propertyName);
          if (property) {
            currentYearMonthly.push({
              month,
              monthName: `${month}月`,
              occ_rate: property.occ_rate,
              adr: property.adr,
              revpar: property.revpar,
              revenue: property.room_revenue,
              bookings: property.booking_count,
              avg_lead_time: property.avg_lead_time
            });
          }
        }
      }

      // Fetch last year data
      const lastYearMonthly: MonthlyData[] = [];
      for (let month = 1; month <= 12; month++) {
        const response = await fetch(`${phpApiUrl}?year=${currentYear - 1}&month=${month}`);
        const data = await response.json();

        if (!data.error && data.properties) {
          const property = data.properties.find((p: PropertyMetrics) => p.property_name === propertyName);
          if (property) {
            lastYearMonthly.push({
              month,
              monthName: `${month}月`,
              occ_rate: property.occ_rate,
              adr: property.adr,
              revpar: property.revpar,
              revenue: property.room_revenue,
              bookings: property.booking_count,
              avg_lead_time: property.avg_lead_time
            });
          }
        }
      }

      setCurrentYearData(currentYearMonthly);
      setLastYearData(lastYearMonthly);

      // Calculate price recommendations
      calculatePriceRecommendations(currentYearMonthly, lastYearMonthly);
    } catch (error) {
      console.error('Error fetching analytics data:', error);
    } finally {
      setLoading(false);
    }
  };

  const calculatePriceRecommendations = (currentData: MonthlyData[], lastYearData: MonthlyData[]) => {
    const recommendations: PriceRecommendation[] = [];
    const currentMonth = new Date().getMonth() + 1;

    // Generate recommendations for next 3 months
    for (let i = 1; i <= 3; i++) {
      const targetMonth = ((currentMonth + i - 1) % 12) + 1;
      const current = currentData[targetMonth - 1];
      const lastYear = lastYearData[targetMonth - 1];

      if (!current || !lastYear) continue;

      let recommendation: PriceRecommendation;
      const avgOccRate = current.occ_rate;
      const yoyOccChange = current.occ_rate - lastYear.occ_rate;
      const currentADR = current.adr || lastYear.adr;
      const avgLeadTime = current.avg_lead_time;
      const yoyLeadTimeChange = current.avg_lead_time - lastYear.avg_lead_time;

      // Lead time insights
      const hasLongLeadTime = avgLeadTime > 30; // More than 30 days advance booking
      const hasShortLeadTime = avgLeadTime < 7; // Less than 7 days advance booking
      const leadTimeIncreasing = yoyLeadTimeChange > 5; // Lead time increasing YoY
      const leadTimeDecreasing = yoyLeadTimeChange < -5; // Lead time decreasing YoY

      // Enhanced pricing logic incorporating lead time
      if (avgOccRate >= 80) {
        // High occupancy
        let increasePercent = 10;
        let reason = `High occupancy (${avgOccRate.toFixed(1)}%)`;

        if (hasLongLeadTime) {
          increasePercent = 15; // Can increase more with advance bookings
          reason += ` with strong advance bookings (${avgLeadTime.toFixed(0)} days lead time) - significant price increase opportunity`;
        } else if (hasShortLeadTime) {
          increasePercent = 8; // Moderate increase for last-minute bookings
          reason += ` but short lead time (${avgLeadTime.toFixed(0)} days) - moderate price increase to avoid losing last-minute bookers`;
        } else {
          reason += ` - strong demand allows for price increase`;
        }

        recommendation = {
          currentADR,
          recommendedADR: currentADR * (1 + increasePercent / 100),
          change: currentADR * (increasePercent / 100),
          changePercent: increasePercent,
          reason,
          indicator: 'increase'
        };
      } else if (avgOccRate < 50) {
        // Low occupancy
        let decreasePercent = yoyOccChange < -10 ? 15 : 10;
        let reason = `Low occupancy (${avgOccRate.toFixed(1)}%)`;

        if (hasShortLeadTime) {
          decreasePercent += 5; // Aggressive discount needed for last-minute fill
          reason += ` with short lead time (${avgLeadTime.toFixed(0)} days) - aggressive price reduction to capture last-minute bookings`;
        } else if (leadTimeDecreasing) {
          decreasePercent += 3; // Lead time declining suggests need for better pricing
          reason += ` with declining lead time (${yoyLeadTimeChange.toFixed(0)} days drop) - price adjustment to improve booking window`;
        } else {
          reason += ` - price reduction recommended to increase bookings`;
        }

        recommendation = {
          currentADR,
          recommendedADR: currentADR * (1 - decreasePercent / 100),
          change: -currentADR * (decreasePercent / 100),
          changePercent: -decreasePercent,
          reason,
          indicator: 'decrease'
        };
      } else if (yoyOccChange > 15 || leadTimeIncreasing) {
        // Strong YoY growth or improving lead time
        let increasePercent = 5;
        let reason = '';

        if (leadTimeIncreasing && yoyOccChange > 0) {
          increasePercent = 8; // Both metrics improving
          reason = `Improving lead time (+${yoyLeadTimeChange.toFixed(0)} days to ${avgLeadTime.toFixed(0)} days) and occupancy (+${yoyOccChange.toFixed(1)}%) - strong market position allows price increase`;
        } else if (leadTimeIncreasing) {
          reason = `Improving lead time (+${yoyLeadTimeChange.toFixed(0)} days to ${avgLeadTime.toFixed(0)} days) indicates growing demand - gradual price increase recommended`;
        } else {
          reason = `Strong YoY growth (+${yoyOccChange.toFixed(1)}%) - gradual price increase recommended`;
        }

        recommendation = {
          currentADR,
          recommendedADR: currentADR * (1 + increasePercent / 100),
          change: currentADR * (increasePercent / 100),
          changePercent: increasePercent,
          reason,
          indicator: 'increase'
        };
      } else if (yoyOccChange < -15 || leadTimeDecreasing) {
        // Declining performance or worsening lead time
        let decreasePercent = 8;
        let reason = '';

        if (leadTimeDecreasing && yoyOccChange < 0) {
          decreasePercent = 12; // Both metrics declining
          reason = `Declining lead time (${yoyLeadTimeChange.toFixed(0)} days to ${avgLeadTime.toFixed(0)} days) and occupancy (${yoyOccChange.toFixed(1)}%) - competitive pricing needed`;
        } else if (leadTimeDecreasing) {
          decreasePercent = 10;
          reason = `Declining lead time (${yoyLeadTimeChange.toFixed(0)} days to ${avgLeadTime.toFixed(0)} days) suggests market pressure - price adjustment recommended`;
        } else {
          reason = `Declining performance (${yoyOccChange.toFixed(1)}%) - price adjustment needed`;
        }

        recommendation = {
          currentADR,
          recommendedADR: currentADR * (1 - decreasePercent / 100),
          change: -currentADR * (decreasePercent / 100),
          changePercent: -decreasePercent,
          reason,
          indicator: 'decrease'
        };
      } else {
        // Stable performance
        let reason = `Stable performance (${avgOccRate.toFixed(1)}% occupancy, ${avgLeadTime.toFixed(0)} days lead time)`;

        if (hasLongLeadTime) {
          reason += ` with healthy advance bookings - maintain current pricing`;
        } else {
          reason += ` - maintain current pricing`;
        }

        recommendation = {
          currentADR,
          recommendedADR: currentADR,
          change: 0,
          changePercent: 0,
          reason,
          indicator: 'maintain'
        };
      }

      recommendations.push(recommendation);
    }

    setPriceRecommendations(recommendations);
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

  const getMetricLabel = () => {
    switch (selectedMetric) {
      case 'occ_rate': return 'Occupancy Rate (%)';
      case 'adr': return 'ADR (¥)';
      case 'revpar': return 'RevPAR (¥)';
      case 'revenue': return 'Revenue (¥)';
      case 'avg_lead_time': return 'Avg Lead Time (days)';
    }
  };

  const yoyChange = calculateYoYChange();

  if (loading) {
    return (
      <div className="flex items-center justify-center h-96">
        <div className="text-gray-600">Loading analytics...</div>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-4">
          <button
            onClick={onBack}
            className="flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors"
          >
            <ArrowLeft className="h-4 w-4" />
            Back
          </button>
          <h2 className="text-2xl font-bold text-gray-900">{propertyName} - Analytics & Insights</h2>
        </div>
      </div>

      {/* YoY Summary */}
      <div className="bg-white rounded-lg shadow p-6">
        <div className="flex items-center justify-between mb-4">
          <h3 className="text-lg font-semibold text-gray-900">Year-over-Year Performance</h3>
          <div className={`flex items-center gap-2 px-4 py-2 rounded-lg ${yoyChange.isPositive ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'}`}>
            {yoyChange.isPositive ? <TrendingUp className="h-5 w-5" /> : <TrendingDown className="h-5 w-5" />}
            <span className="font-bold">{yoyChange.change.toFixed(1)}%</span>
          </div>
        </div>

        {/* Metric Selector */}
        <div className="mb-6 flex gap-2 flex-wrap">
          <button
            onClick={() => setSelectedMetric('occ_rate')}
            className={`px-4 py-2 rounded-lg font-medium transition-colors ${
              selectedMetric === 'occ_rate' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
            }`}
          >
            Occupancy
          </button>
          <button
            onClick={() => setSelectedMetric('adr')}
            className={`px-4 py-2 rounded-lg font-medium transition-colors ${
              selectedMetric === 'adr' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
            }`}
          >
            ADR
          </button>
          <button
            onClick={() => setSelectedMetric('revpar')}
            className={`px-4 py-2 rounded-lg font-medium transition-colors ${
              selectedMetric === 'revpar' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
            }`}
          >
            RevPAR
          </button>
          <button
            onClick={() => setSelectedMetric('revenue')}
            className={`px-4 py-2 rounded-lg font-medium transition-colors ${
              selectedMetric === 'revenue' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
            }`}
          >
            Revenue
          </button>
          <button
            onClick={() => setSelectedMetric('avg_lead_time')}
            className={`px-4 py-2 rounded-lg font-medium transition-colors ${
              selectedMetric === 'avg_lead_time' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
            }`}
          >
            Lead Time
          </button>
        </div>

        {/* Comparison Chart */}
        <ResponsiveContainer width="100%" height={400}>
          <LineChart data={getComparisonData()}>
            <CartesianGrid strokeDasharray="3 3" />
            <XAxis dataKey="month" />
            <YAxis />
            <Tooltip />
            <Legend />
            <Line type="monotone" dataKey={`${currentYear}`} stroke="#3b82f6" strokeWidth={2} name={`${currentYear}`} />
            <Line type="monotone" dataKey={`${currentYear - 1}`} stroke="#9ca3af" strokeWidth={2} name={`${currentYear - 1}`} strokeDasharray="5 5" />
          </LineChart>
        </ResponsiveContainer>
      </div>

      {/* Price Recommendations */}
      <div className="bg-white rounded-lg shadow p-6">
        <div className="flex items-center gap-2 mb-4">
          <DollarSign className="h-6 w-6 text-blue-600" />
          <h3 className="text-lg font-semibold text-gray-900">Dynamic Pricing Recommendations</h3>
        </div>

        <div className="space-y-4">
          {priceRecommendations.map((rec, index) => {
            const targetMonth = ((new Date().getMonth() + index + 1) % 12) + 1;
            return (
              <div key={index} className="border border-gray-200 rounded-lg p-4">
                <div className="flex items-start justify-between mb-3">
                  <div className="flex items-center gap-3">
                    <Calendar className="h-5 w-5 text-gray-400" />
                    <div>
                      <h4 className="font-semibold text-gray-900">{targetMonth}月 ({new Date(2024, targetMonth - 1).toLocaleString('en', { month: 'long' })})</h4>
                      <p className="text-sm text-gray-600 mt-1">{rec.reason}</p>
                    </div>
                  </div>
                  <div className={`flex items-center gap-2 px-3 py-1 rounded-full text-sm font-medium ${
                    rec.indicator === 'increase' ? 'bg-green-100 text-green-700' :
                    rec.indicator === 'decrease' ? 'bg-red-100 text-red-700' :
                    'bg-gray-100 text-gray-700'
                  }`}>
                    {rec.indicator === 'increase' && <TrendingUp className="h-4 w-4" />}
                    {rec.indicator === 'decrease' && <TrendingDown className="h-4 w-4" />}
                    {rec.indicator === 'maintain' && <AlertCircle className="h-4 w-4" />}
                    {rec.changePercent > 0 ? '+' : ''}{rec.changePercent.toFixed(0)}%
                  </div>
                </div>

                <div className="grid grid-cols-3 gap-4 mt-4 pt-4 border-t border-gray-100">
                  <div>
                    <p className="text-xs text-gray-500 mb-1">Current ADR</p>
                    <p className="text-lg font-semibold text-gray-900">{formatCurrency(rec.currentADR)}</p>
                  </div>
                  <div>
                    <p className="text-xs text-gray-500 mb-1">Recommended ADR</p>
                    <p className="text-lg font-semibold text-blue-600">{formatCurrency(rec.recommendedADR)}</p>
                  </div>
                  <div>
                    <p className="text-xs text-gray-500 mb-1">Price Change</p>
                    <p className={`text-lg font-semibold ${rec.change > 0 ? 'text-green-600' : rec.change < 0 ? 'text-red-600' : 'text-gray-600'}`}>
                      {rec.change > 0 ? '+' : ''}{formatCurrency(rec.change)}
                    </p>
                  </div>
                </div>
              </div>
            );
          })}
        </div>
      </div>

      {/* Monthly Performance Table */}
      <div className="bg-white rounded-lg shadow p-6">
        <h3 className="text-lg font-semibold text-gray-900 mb-4">Detailed Monthly Comparison</h3>
        <div className="overflow-x-auto">
          <table className="min-w-full divide-y divide-gray-200">
            <thead className="bg-gray-50">
              <tr>
                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Month</th>
                <th className="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">OCC {currentYear}</th>
                <th className="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">OCC {currentYear - 1}</th>
                <th className="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Change</th>
                <th className="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">ADR {currentYear}</th>
                <th className="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">ADR {currentYear - 1}</th>
                <th className="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Revenue {currentYear}</th>
              </tr>
            </thead>
            <tbody className="bg-white divide-y divide-gray-200">
              {currentYearData.map((current, index) => {
                const lastYear = lastYearData[index];
                const occChange = current.occ_rate - (lastYear?.occ_rate || 0);
                return (
                  <tr key={index} className="hover:bg-gray-50">
                    <td className="px-4 py-3 font-medium text-gray-900">{current.monthName}</td>
                    <td className="px-4 py-3 text-right text-gray-900">{formatPercent(current.occ_rate)}</td>
                    <td className="px-4 py-3 text-right text-gray-600">{lastYear ? formatPercent(lastYear.occ_rate) : '-'}</td>
                    <td className={`px-4 py-3 text-right font-medium ${occChange > 0 ? 'text-green-600' : occChange < 0 ? 'text-red-600' : 'text-gray-600'}`}>
                      {occChange > 0 ? '+' : ''}{occChange.toFixed(1)}%
                    </td>
                    <td className="px-4 py-3 text-right text-gray-900">{formatCurrency(current.adr)}</td>
                    <td className="px-4 py-3 text-right text-gray-600">{lastYear ? formatCurrency(lastYear.adr) : '-'}</td>
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
