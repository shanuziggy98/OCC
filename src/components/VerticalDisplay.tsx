'use client';

import React, { useState, useEffect } from 'react';
import { LineChart, Line, XAxis, YAxis, CartesianGrid, Tooltip, Legend, ResponsiveContainer } from 'recharts';
import { X, ArrowLeft, RefreshCw, TrendingUp } from 'lucide-react';
import RoomAnalytics from './RoomAnalytics';

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

interface MonthData {
  month: string;
  occ: number;
  adr: number;
  revpar: number;
  totalSales: number;
  avgLeadTime: number;
  cleaningFee: number;
  commissions: number;
}

interface VerticalDisplayProps {
  propertyData?: {
    [propertyName: string]: MonthData[];
  };
  onBack?: () => void;
  selectedYear?: number;
}

export default function VerticalDisplay({ propertyData: propPropertyData, onBack, selectedYear = new Date().getFullYear() }: VerticalDisplayProps) {
  const [selectedProperty, setSelectedProperty] = useState<string | null>(null);
  const [selectedMetric, setSelectedMetric] = useState<'occ' | 'adr' | 'revpar' | 'totalSales'>('occ');
  const [propertyData, setPropertyData] = useState<{ [key: string]: MonthData[] }>({});
  const [loading, setLoading] = useState(false);
  const [showRoomsModal, setShowRoomsModal] = useState(false);
  const [selectedPropertyForRooms, setSelectedPropertyForRooms] = useState<string | null>(null);
  const [roomsData, setRoomsData] = useState<{ [roomType: string]: MonthData[] }>({});
  const [loadingRooms, setLoadingRooms] = useState(false);
  const [selectedRoom, setSelectedRoom] = useState<string | null>(null);
  const [selectedRoomMetric, setSelectedRoomMetric] = useState<'occ' | 'adr' | 'revpar' | 'totalSales'>('occ');
  const [showRoomAnalytics, setShowRoomAnalytics] = useState(false);
  const [selectedRoomForAnalytics, setSelectedRoomForAnalytics] = useState<string | null>(null);


  const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('ja-JP', { style: 'currency', currency: 'JPY' }).format(amount);
  };

  const formatPercent = (rate: number) => {
    return `${rate.toFixed(2)}%`;
  };

  const handleRowClick = (property: string) => {
    setSelectedProperty(property);
  };

  const closeGraph = () => {
    setSelectedProperty(null);
  };

  const fetchPropertyRooms = async (propertyName: string) => {
    const expandableProperties = ['iwatoyama', 'Goettingen', 'littlehouse', 'kaguya'];
    if (!expandableProperties.includes(propertyName)) {
      return; // Not an expandable property
    }

    setLoadingRooms(true);
    setSelectedPropertyForRooms(propertyName);
    setShowRoomsModal(true);

    try {
      const phpApiUrl = process.env.NEXT_PUBLIC_PHP_API_URL || 'https://exseed.main.jp/WG/analysis/OCC/occupancy_metrics_api.php';
      const allRoomData: { [roomType: string]: MonthData[] } = {};

      // Fetch data for all 12 months
      for (let month = 1; month <= 12; month++) {
        const response = await fetch(`${phpApiUrl}?action=${propertyName.toLowerCase()}_rooms&year=${selectedYear}&month=${month}`);
        const data = await response.json();

        if (!data.error && data.rooms) {
          // Process each room for this month
          data.rooms.forEach((room: PropertyMetrics) => {
            const roomType = room.room_type || 'Unknown Room';

            if (!allRoomData[roomType]) {
              allRoomData[roomType] = Array.from({ length: 12 }, (_, i) => ({
                month: `${i + 1}月`,
                occ: 0,
                adr: 0,
                revpar: 0,
                totalSales: 0,
                avgLeadTime: 0,
                cleaningFee: 0,
                commissions: 0
              }));
            }

            allRoomData[roomType][month - 1] = {
              month: `${month}月`,
              occ: room.occ_rate,
              adr: room.adr,
              revpar: room.revpar,
              totalSales: room.room_revenue,
              avgLeadTime: room.avg_lead_time,
              cleaningFee: room.total_cleaning_fee,
              commissions: room.ota_commission
            };
          });
        }
      }

      setRoomsData(allRoomData);
    } catch (error) {
      console.error(`Error fetching ${propertyName} rooms:`, error);
      setRoomsData({});
    } finally {
      setLoadingRooms(false);
    }
  };

  const closeRoomsModal = () => {
    setShowRoomsModal(false);
    setSelectedPropertyForRooms(null);
    setRoomsData({});
    setSelectedRoom(null);
  };

  const handleRoomClick = (roomType: string) => {
    setSelectedRoom(roomType);
  };

  const closeRoomGraph = () => {
    setSelectedRoom(null);
  };

  const getRoomChartData = () => {
    if (!selectedRoom) return [];
    return roomsData[selectedRoom] || [];
  };

  const getChartData = () => {
    if (!selectedProperty) return [];
    return propertyData[selectedProperty] || [];
  };

  const getMetricLabel = () => {
    switch (selectedMetric) {
      case 'occ': return 'Occupancy Rate (%)';
      case 'adr': return 'ADR (¥)';
      case 'revpar': return 'RevPAR (¥)';
      case 'totalSales': return 'Total Sales (¥)';
    }
  };

  const getMetricValue = (item: MonthData) => {
    switch (selectedMetric) {
      case 'occ': return item.occ;
      case 'adr': return item.adr;
      case 'revpar': return item.revpar;
      case 'totalSales': return item.totalSales;
    }
  };

  const getRoomMetricLabel = () => {
    switch (selectedRoomMetric) {
      case 'occ': return 'Occupancy Rate (%)';
      case 'adr': return 'ADR (¥)';
      case 'revpar': return 'RevPAR (¥)';
      case 'totalSales': return 'Total Sales (¥)';
    }
  };

  const getRoomMetricValue = (item: MonthData) => {
    switch (selectedRoomMetric) {
      case 'occ': return item.occ;
      case 'adr': return item.adr;
      case 'revpar': return item.revpar;
      case 'totalSales': return item.totalSales;
    }
  };

  // Use the fetched propertyData
  const sampleData = propertyData;

  const [currentYear, setCurrentYear] = useState(selectedYear);

  // Refetch data when year changes
  useEffect(() => {
    const fetchAllMonthsData = async () => {
      setLoading(true);
      const allPropertyData: { [key: string]: MonthData[] } = {};

      try {
        const phpApiUrl = process.env.NEXT_PUBLIC_PHP_API_URL || 'https://exseed.main.jp/WG/analysis/OCC/occupancy_metrics_api.php';
        // Fetch data for all 12 months
        for (let month = 1; month <= 12; month++) {
          const response = await fetch(`${phpApiUrl}?year=${currentYear}&month=${month}`);
          const data = await response.json();

          if (!data.error && data.properties) {
            // Process each property for this month
            data.properties.forEach((prop: PropertyMetrics) => {
              if (!allPropertyData[prop.property_name]) {
                allPropertyData[prop.property_name] = Array.from({ length: 12 }, (_, i) => ({
                  month: `${i + 1}月`,
                  occ: 0,
                  adr: 0,
                  revpar: 0,
                  totalSales: 0,
                  avgLeadTime: 0,
                  cleaningFee: 0,
                  commissions: 0
                }));
              }

              // Update the data for this month
              allPropertyData[prop.property_name][month - 1] = {
                month: `${month}月`,
                occ: prop.occ_rate || 0,
                adr: prop.adr || 0,
                revpar: prop.revpar || 0,
                totalSales: prop.room_revenue || 0,
                avgLeadTime: prop.avg_lead_time || 0,
                cleaningFee: prop.total_cleaning_fee || 0,
                commissions: prop.ota_commission || 0
              };
            });
          }
        }

        setPropertyData(allPropertyData);
      } catch (error) {
        console.error('Error fetching annual data:', error);
      } finally {
        setLoading(false);
      }
    };

    fetchAllMonthsData();
  }, [currentYear]);

  return (
    <div className="min-h-screen bg-gray-50 p-6">
      {/* Header with Back Button and Year Selector */}
      <div className="mb-4 flex items-center justify-between">
        {onBack && (
          <button
            onClick={onBack}
            className="flex items-center gap-2 px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors"
          >
            <ArrowLeft className="h-4 w-4" />
            Back to Horizontal View
          </button>
        )}

        {/* Year Selector */}
        <div className="flex items-center gap-3">
          <label className="text-sm font-medium text-gray-700">Select Year:</label>
          <select
            value={currentYear}
            onChange={(e) => setCurrentYear(parseInt(e.target.value))}
            className="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-gray-900 font-medium"
          >
            {Array.from({ length: 5 }, (_, i) => new Date().getFullYear() - 2 + i).map(year => (
              <option key={year} value={year}>{year}</option>
            ))}
          </select>
        </div>
      </div>

      {/* Loading Indicator */}
      {loading && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
          <div className="bg-white rounded-lg p-6">
            <RefreshCw className="h-8 w-8 animate-spin mx-auto mb-2 text-blue-600" />
            <p className="text-gray-900">Loading annual data for {currentYear}...</p>
          </div>
        </div>
      )}

      {/* Graph Modal */}
      {selectedProperty && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-6">
          <div className="bg-white rounded-lg p-6 w-full max-w-6xl max-h-[90vh] overflow-y-auto">
            <div className="flex items-center justify-between mb-6">
              <div>
                <h2 className="text-2xl font-bold text-gray-900">{selectedProperty} - Annual Performance</h2>
                <p className="text-sm text-gray-600 mt-1">Click on different metrics to view trends</p>
              </div>
              <button
                onClick={closeGraph}
                className="text-gray-500 hover:text-gray-700 p-2 hover:bg-gray-100 rounded-lg transition-colors"
              >
                <X className="h-6 w-6" />
              </button>
            </div>

            {/* Metric Selector */}
            <div className="flex gap-3 mb-6">
              <button
                onClick={() => setSelectedMetric('occ')}
                className={`px-4 py-2 rounded-lg font-medium transition-colors ${
                  selectedMetric === 'occ' ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'
                }`}
              >
                Occupancy Rate
              </button>
              <button
                onClick={() => setSelectedMetric('adr')}
                className={`px-4 py-2 rounded-lg font-medium transition-colors ${
                  selectedMetric === 'adr' ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'
                }`}
              >
                ADR
              </button>
              <button
                onClick={() => setSelectedMetric('revpar')}
                className={`px-4 py-2 rounded-lg font-medium transition-colors ${
                  selectedMetric === 'revpar' ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'
                }`}
              >
                RevPAR
              </button>
              <button
                onClick={() => setSelectedMetric('totalSales')}
                className={`px-4 py-2 rounded-lg font-medium transition-colors ${
                  selectedMetric === 'totalSales' ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'
                }`}
              >
                Total Sales
              </button>
            </div>

            {/* Chart */}
            <div className="bg-gray-50 rounded-lg p-6">
              <h3 className="text-lg font-semibold text-gray-900 mb-4">{getMetricLabel()}</h3>
              <ResponsiveContainer width="100%" height={400}>
                <LineChart data={getChartData()}>
                  <CartesianGrid strokeDasharray="3 3" />
                  <XAxis dataKey="month" />
                  <YAxis />
                  <Tooltip
                    formatter={(value: number) =>
                      selectedMetric === 'occ' ? `${value.toFixed(2)}%` : formatCurrency(value)
                    }
                  />
                  <Legend />
                  <Line
                    type="monotone"
                    dataKey={(item: MonthData) => getMetricValue(item)}
                    stroke="#2563eb"
                    strokeWidth={3}
                    name={getMetricLabel()}
                    dot={{ r: 6 }}
                    activeDot={{ r: 8 }}
                  />
                </LineChart>
              </ResponsiveContainer>
            </div>
          </div>
        </div>
      )}

      {/* Vertical Table Layout */}
      <div className="bg-white rounded-lg shadow-sm border p-6">
        <h2 className="text-2xl font-bold text-gray-900 mb-6">{currentYear} Annual Property Comparison</h2>

        {/* No Data Message */}
        {!loading && Object.keys(sampleData).length === 0 && (
          <div className="text-center py-12">
            <p className="text-gray-500 text-lg">No property data available for {currentYear}</p>
            <p className="text-gray-400 text-sm mt-2">Properties will appear here automatically when data is added to the database</p>
          </div>
        )}

        {Object.keys(sampleData).length > 0 && (
          <>

        {/* Summary Row */}
        <div className="mb-6 p-4 bg-blue-50 rounded-lg">
          <div className="grid grid-cols-4 gap-4 text-center">
            <div>
              <p className="text-sm text-gray-600">Total OCC</p>
              <p className="text-2xl font-bold text-blue-600">
                {(() => {
                  const propertyNames = Object.keys(propertyData);
                  if (propertyNames.length === 0) return '0%';
                  const avgOcc = propertyNames.reduce((sum, propName) => {
                    const propAvg = sampleData[propName]?.reduce((s, m) => s + m.occ, 0) / (sampleData[propName]?.length || 1) || 0;
                    return sum + propAvg;
                  }, 0) / propertyNames.length;
                  return `${avgOcc.toFixed(2)}%`;
                })()}
              </p>
            </div>
            <div>
              <p className="text-sm text-gray-600">Total Sales</p>
              <p className="text-2xl font-bold text-green-600">
                {(() => {
                  const propertyNames = Object.keys(propertyData);
                  const totalSales = propertyNames.reduce((sum, propName) => {
                    const propTotal = sampleData[propName]?.reduce((s, m) => s + m.totalSales, 0) || 0;
                    return sum + propTotal;
                  }, 0);
                  return formatCurrency(totalSales);
                })()}
              </p>
            </div>
            <div>
              <p className="text-sm text-gray-600">Total Cleaning Fee</p>
              <p className="text-2xl font-bold text-orange-600">
                {(() => {
                  const propertyNames = Object.keys(propertyData);
                  const totalCleaning = propertyNames.reduce((sum, propName) => {
                    const propTotal = sampleData[propName]?.reduce((s, m) => s + m.cleaningFee, 0) || 0;
                    return sum + propTotal;
                  }, 0);
                  return formatCurrency(totalCleaning);
                })()}
              </p>
            </div>
            <div>
              <p className="text-sm text-gray-600">Total Commissions</p>
              <p className="text-2xl font-bold text-red-600">
                {(() => {
                  const propertyNames = Object.keys(propertyData);
                  const totalCommissions = propertyNames.reduce((sum, propName) => {
                    const propTotal = sampleData[propName]?.reduce((s, m) => s + m.commissions, 0) || 0;
                    return sum + propTotal;
                  }, 0);
                  return formatCurrency(totalCommissions);
                })()}
              </p>
            </div>
          </div>
        </div>

        {/* Property Comparison Table */}
        <div className="overflow-x-auto max-h-[70vh] relative border border-gray-300 rounded-lg">
          <table className="w-full text-sm border-collapse">
            <thead className="sticky top-0 z-20 shadow-md">
              <tr className="bg-gray-100">
                <th className="border border-gray-300 px-4 py-3 text-left font-semibold text-gray-900 sticky left-0 z-30 bg-gray-100 shadow-[2px_0_5px_-2px_rgba(0,0,0,0.1)]">Month</th>
                <th className="border border-gray-300 px-4 py-3 text-left font-semibold text-gray-900 sticky left-[80px] z-30 bg-gray-100 shadow-[2px_0_5px_-2px_rgba(0,0,0,0.1)]">Total</th>
                {Object.keys(sampleData).map((propName, index) => {
                  const expandableProperties = ['iwatoyama', 'Goettingen', 'littlehouse', 'kaguya'];
                  const isExpandable = expandableProperties.includes(propName);
                  const bgColor = index % 2 === 0 ? 'bg-blue-100' : 'bg-green-100';

                  return (
                    <th key={propName} className={`border border-gray-300 px-4 py-3 text-center font-semibold text-gray-900 ${bgColor}`} colSpan={2}>
                      {isExpandable ? (
                        <button
                          onClick={() => fetchPropertyRooms(propName)}
                          className="text-blue-600 hover:text-blue-800 underline cursor-pointer transition-colors"
                          title="Click to view room details"
                        >
                          {propName}
                        </button>
                      ) : (
                        propName
                      )}
                    </th>
                  );
                })}
              </tr>
              <tr className="bg-gray-50">
                <th className="border border-gray-300 px-4 py-2 text-left font-medium text-gray-700 sticky left-0 z-30 bg-gray-50 shadow-[2px_0_5px_-2px_rgba(0,0,0,0.1)]">Metric</th>
                <th className="border border-gray-300 px-4 py-2 text-left font-medium text-gray-700 sticky left-[80px] z-30 bg-gray-50 shadow-[2px_0_5px_-2px_rgba(0,0,0,0.1)]">合計</th>
                {Object.keys(sampleData).map((propName, index) => {
                  const yearlyTotalSales = sampleData[propName].reduce((sum, m) => sum + m.totalSales, 0);
                  const yearlyTotalCleaning = sampleData[propName].reduce((sum, m) => sum + m.cleaningFee, 0);
                  const yearlyTotalCommissions = sampleData[propName].reduce((sum, m) => sum + m.commissions, 0);
                  const bgColor = index % 2 === 0 ? 'bg-blue-50' : 'bg-green-50';

                  return (
                    <React.Fragment key={`${propName}-header`}>
                      <th className={`border border-gray-300 px-2 py-2 text-center font-medium text-gray-700 ${bgColor}`} colSpan={2}>
                        <div className="space-y-1">
                          <div className="text-xs text-blue-700">
                            <div className="font-semibold">Year Total Sales:</div>
                            <div>{formatCurrency(yearlyTotalSales)}</div>
                          </div>
                          <div className="text-xs text-orange-700">
                            <div className="font-semibold">Year Cleaning:</div>
                            <div>{formatCurrency(yearlyTotalCleaning)}</div>
                          </div>
                          <div className="text-xs text-red-700">
                            <div className="font-semibold">Year Commission:</div>
                            <div>{formatCurrency(yearlyTotalCommissions)}</div>
                          </div>
                        </div>
                      </th>
                    </React.Fragment>
                  );
                })}
              </tr>
              <tr className="bg-gray-100">
                <th className="border border-gray-300 px-4 py-2 text-left font-medium text-gray-700 sticky left-0 z-30 bg-gray-100 shadow-[2px_0_5px_-2px_rgba(0,0,0,0.1)]">Month</th>
                <th className="border border-gray-300 px-4 py-2 text-left font-medium text-gray-700 sticky left-[80px] z-30 bg-gray-100 shadow-[2px_0_5px_-2px_rgba(0,0,0,0.1)]">Data</th>
                {Object.keys(sampleData).map((propName, index) => {
                  const bgColor = index % 2 === 0 ? 'bg-blue-100' : 'bg-green-100';
                  return (
                    <React.Fragment key={`${propName}-subheader`}>
                      <th className={`border border-gray-300 px-4 py-2 text-center font-medium text-gray-700 ${bgColor}`}>OCC</th>
                      <th className={`border border-gray-300 px-4 py-2 text-center font-medium text-gray-700 ${bgColor}`}>Amount</th>
                    </React.Fragment>
                  );
                })}
              </tr>
            </thead>
            <tbody>
              {sampleData[Object.keys(sampleData)[0]]?.map((_, monthIndex) => {
                const propertyNames = Object.keys(sampleData);
                return (
                  <tr
                    key={monthIndex}
                    className="hover:bg-yellow-50 transition-colors"
                  >
                    <td className="border border-gray-300 px-4 py-3 font-medium text-gray-900 sticky left-0 z-10 bg-white shadow-[2px_0_5px_-2px_rgba(0,0,0,0.1)]">
                      {sampleData[propertyNames[0]][monthIndex].month}
                    </td>
                    <td className="border border-gray-300 px-4 py-3 text-gray-900 sticky left-[80px] z-10 bg-white shadow-[2px_0_5px_-2px_rgba(0,0,0,0.1)]">
                      <div className="space-y-1">
                        <div className="flex justify-between">
                          <span className="text-xs text-gray-600">OCC:</span>
                          <span className="font-semibold">
                            {formatPercent(
                              propertyNames.reduce((sum, propName) =>
                                sum + (sampleData[propName][monthIndex]?.occ || 0), 0
                              ) / propertyNames.length
                            )}
                          </span>
                        </div>
                        <div className="flex justify-between">
                          <span className="text-xs text-gray-600">Sales:</span>
                          <span className="font-semibold">
                            {formatCurrency(
                              propertyNames.reduce((sum, propName) =>
                                sum + (sampleData[propName][monthIndex]?.totalSales || 0), 0
                              )
                            )}
                          </span>
                        </div>
                      </div>
                    </td>
                    {propertyNames.map((propName, propIndex) => {
                      const item = sampleData[propName][monthIndex];
                      const bgColor = propIndex % 2 === 0 ? 'bg-blue-50' : 'bg-green-50';
                      const hoverColor = propIndex % 2 === 0 ? 'hover:bg-blue-100' : 'hover:bg-green-100';
                      return (
                        <React.Fragment key={`${propName}-${monthIndex}`}>
                          <td
                            className={`border border-gray-300 px-4 py-3 text-center cursor-pointer ${bgColor} ${hoverColor}`}
                            onClick={() => handleRowClick(propName)}
                          >
                            <span className={`font-bold ${item.occ >= 50 ? 'text-green-700' : item.occ >= 30 ? 'text-yellow-700' : 'text-red-700'}`}>
                              {formatPercent(item.occ)}
                            </span>
                          </td>
                          <td
                            className={`border border-gray-300 px-4 py-3 text-right text-gray-900 cursor-pointer ${bgColor} ${hoverColor}`}
                            onClick={() => handleRowClick(propName)}
                          >
                            <div className="space-y-1 text-xs">
                              <div><span className="text-gray-600">Sales:</span> {formatCurrency(item.totalSales)}</div>
                              <div><span className="text-gray-600">ADR:</span> {formatCurrency(item.adr)}</div>
                              <div><span className="text-gray-600">RevPAR:</span> {formatCurrency(item.revpar)}</div>
                              <div><span className="text-gray-600">Lead Time:</span> {item.avgLeadTime.toFixed(1)}</div>
                              <div><span className="text-gray-600">Cleaning:</span> {formatCurrency(item.cleaningFee)}</div>
                              <div><span className="text-gray-600">Commission:</span> {formatCurrency(item.commissions)}</div>
                            </div>
                          </td>
                        </React.Fragment>
                      );
                    })}
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>

        <div className="mt-4 text-sm text-gray-600">
          <p className="font-medium">Click on any property cell to view detailed performance charts</p>
          <p className="text-xs mt-1">For properties with multiple rooms (iwatoyama, Goettingen, littlehouse, kaguya), click on the property name to view room details</p>
        </div>
        </>
        )}
      </div>

      {/* Rooms Modal */}
      {showRoomsModal && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
          <div className="bg-white rounded-lg shadow-xl max-w-6xl w-full max-h-[90vh] overflow-hidden">
            <div className="flex justify-between items-center p-6 border-b border-gray-200">
              <h2 className="text-2xl font-bold text-gray-900">
                {selectedPropertyForRooms} - Room Details
              </h2>
              <button
                onClick={closeRoomsModal}
                className="text-gray-500 hover:text-gray-700 transition-colors"
              >
                <X className="h-6 w-6" />
              </button>
            </div>

            <div className="p-6 overflow-y-auto max-h-[calc(90vh-120px)]">
              {showRoomAnalytics && selectedRoomForAnalytics && selectedPropertyForRooms ? (
                <RoomAnalytics
                  propertyName={selectedPropertyForRooms}
                  roomType={selectedRoomForAnalytics}
                  currentYear={currentYear}
                  onBack={() => {
                    setShowRoomAnalytics(false);
                    setSelectedRoomForAnalytics(null);
                  }}
                />
              ) : loadingRooms ? (
                <div className="flex items-center justify-center py-12">
                  <RefreshCw className="h-8 w-8 animate-spin text-blue-600" />
                  <span className="ml-3 text-gray-600">Loading room details...</span>
                </div>
              ) : Object.keys(roomsData).length === 0 ? (
                <div className="text-center py-12 text-gray-500">
                  No room data available
                </div>
              ) : selectedRoom ? (
                <div>
                  <button
                    onClick={closeRoomGraph}
                    className="mb-4 flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors"
                  >
                    <ArrowLeft className="h-4 w-4" />
                    Back to Room List
                  </button>

                  <h3 className="text-xl font-bold text-gray-900 mb-4">{selectedRoom} - Monthly Performance</h3>

                  {/* Metric Selector for Room */}
                  <div className="mb-4 flex gap-2">
                    <button
                      onClick={() => setSelectedRoomMetric('occ')}
                      className={`px-4 py-2 rounded-lg font-medium transition-colors ${
                        selectedRoomMetric === 'occ'
                          ? 'bg-blue-600 text-white'
                          : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                      }`}
                    >
                      OCC Rate
                    </button>
                    <button
                      onClick={() => setSelectedRoomMetric('adr')}
                      className={`px-4 py-2 rounded-lg font-medium transition-colors ${
                        selectedRoomMetric === 'adr'
                          ? 'bg-blue-600 text-white'
                          : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                      }`}
                    >
                      ADR
                    </button>
                    <button
                      onClick={() => setSelectedRoomMetric('revpar')}
                      className={`px-4 py-2 rounded-lg font-medium transition-colors ${
                        selectedRoomMetric === 'revpar'
                          ? 'bg-blue-600 text-white'
                          : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                      }`}
                    >
                      RevPAR
                    </button>
                    <button
                      onClick={() => setSelectedRoomMetric('totalSales')}
                      className={`px-4 py-2 rounded-lg font-medium transition-colors ${
                        selectedRoomMetric === 'totalSales'
                          ? 'bg-blue-600 text-white'
                          : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                      }`}
                    >
                      Total Sales
                    </button>
                  </div>

                  <div className="mb-6">
                    <ResponsiveContainer width="100%" height={300}>
                      <LineChart data={getRoomChartData()}>
                        <CartesianGrid strokeDasharray="3 3" />
                        <XAxis dataKey="month" />
                        <YAxis />
                        <Tooltip />
                        <Legend />
                        <Line
                          type="monotone"
                          dataKey={selectedRoomMetric}
                          stroke="#3b82f6"
                          name={getRoomMetricLabel()}
                          strokeWidth={2}
                        />
                      </LineChart>
                    </ResponsiveContainer>
                  </div>

                  <div className="overflow-x-auto">
                    <table className="min-w-full divide-y divide-gray-200">
                      <thead className="bg-gray-50">
                        <tr>
                          <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Month</th>
                          <th className="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">OCC %</th>
                          <th className="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">ADR</th>
                          <th className="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">RevPAR</th>
                          <th className="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total Sales</th>
                          <th className="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Lead Time</th>
                          <th className="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Cleaning Fee</th>
                          <th className="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Commissions</th>
                        </tr>
                      </thead>
                      <tbody className="bg-white divide-y divide-gray-200">
                        {getRoomChartData().map((item, index) => (
                          <tr key={index} className="hover:bg-gray-50">
                            <td className="px-4 py-3 font-medium text-gray-900">{item.month}</td>
                            <td className="px-4 py-3 text-right">
                              <span className={item.occ >= 50 ? 'text-green-700 font-bold' : item.occ >= 30 ? 'text-yellow-700 font-bold' : 'text-red-700 font-bold'}>
                                {formatPercent(item.occ)}
                              </span>
                            </td>
                            <td className="px-4 py-3 text-right text-gray-900">{formatCurrency(item.adr)}</td>
                            <td className="px-4 py-3 text-right text-gray-900">{formatCurrency(item.revpar)}</td>
                            <td className="px-4 py-3 text-right text-gray-900">{formatCurrency(item.totalSales)}</td>
                            <td className="px-4 py-3 text-right text-gray-900">{item.avgLeadTime.toFixed(1)}</td>
                            <td className="px-4 py-3 text-right text-gray-900">{formatCurrency(item.cleaningFee)}</td>
                            <td className="px-4 py-3 text-right text-gray-900">{formatCurrency(item.commissions)}</td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                </div>
              ) : (
                <div className="overflow-x-auto max-h-[60vh] relative border border-gray-300 rounded-lg">
                  <table className="w-full text-sm border-collapse">
                    <thead className="sticky top-0 z-20 shadow-md">
                      <tr className="bg-gray-100">
                        <th className="border border-gray-300 px-4 py-3 text-left font-semibold text-gray-900 sticky left-0 z-30 bg-gray-100 shadow-[2px_0_5px_-2px_rgba(0,0,0,0.1)]">Month</th>
                        {Object.keys(roomsData).map((roomType, index) => {
                          const bgColor = index % 2 === 0 ? 'bg-purple-100' : 'bg-pink-100';
                          return (
                            <th key={roomType} className={`border border-gray-300 px-4 py-3 text-center font-semibold text-gray-900 ${bgColor}`} colSpan={2}>
                              <div className="flex items-center justify-center gap-2">
                                <button
                                  onClick={() => handleRoomClick(roomType)}
                                  className="text-blue-600 hover:text-blue-800 underline cursor-pointer transition-colors"
                                  title="Click to view detailed chart"
                                >
                                  {roomType}
                                </button>
                                <button
                                  onClick={() => {
                                    setSelectedRoomForAnalytics(roomType);
                                    setShowRoomAnalytics(true);
                                  }}
                                  className="p-1 text-green-600 hover:text-green-800 hover:bg-green-50 rounded transition-colors"
                                  title="View Analytics"
                                >
                                  <TrendingUp className="h-4 w-4" />
                                </button>
                              </div>
                            </th>
                          );
                        })}
                      </tr>
                      <tr className="bg-gray-50">
                        <th className="border border-gray-300 px-4 py-2 text-left font-medium text-gray-700 sticky left-0 z-30 bg-gray-50 shadow-[2px_0_5px_-2px_rgba(0,0,0,0.1)]">Metric</th>
                        {Object.keys(roomsData).map((roomType, index) => {
                          const yearlyTotalSales = roomsData[roomType].reduce((sum, m) => sum + m.totalSales, 0);
                          const yearlyTotalCleaning = roomsData[roomType].reduce((sum, m) => sum + m.cleaningFee, 0);
                          const yearlyTotalCommissions = roomsData[roomType].reduce((sum, m) => sum + m.commissions, 0);
                          const bgColor = index % 2 === 0 ? 'bg-purple-50' : 'bg-pink-50';

                          return (
                            <React.Fragment key={`${roomType}-header`}>
                              <th className={`border border-gray-300 px-2 py-2 text-center font-medium text-gray-700 ${bgColor}`} colSpan={2}>
                                <div className="space-y-1">
                                  <div className="text-xs text-blue-700">
                                    <div className="font-semibold">Year Total Sales:</div>
                                    <div>{formatCurrency(yearlyTotalSales)}</div>
                                  </div>
                                  <div className="text-xs text-orange-700">
                                    <div className="font-semibold">Year Cleaning:</div>
                                    <div>{formatCurrency(yearlyTotalCleaning)}</div>
                                  </div>
                                  <div className="text-xs text-red-700">
                                    <div className="font-semibold">Year Commission:</div>
                                    <div>{formatCurrency(yearlyTotalCommissions)}</div>
                                  </div>
                                </div>
                              </th>
                            </React.Fragment>
                          );
                        })}
                      </tr>
                      <tr className="bg-gray-100">
                        <th className="border border-gray-300 px-4 py-2 text-left font-medium text-gray-700 sticky left-0 z-30 bg-gray-100 shadow-[2px_0_5px_-2px_rgba(0,0,0,0.1)]">Month</th>
                        {Object.keys(roomsData).map((roomType, index) => {
                          const bgColor = index % 2 === 0 ? 'bg-purple-100' : 'bg-pink-100';
                          return (
                            <React.Fragment key={`${roomType}-subheader`}>
                              <th className={`border border-gray-300 px-4 py-2 text-center font-medium text-gray-700 ${bgColor}`}>OCC</th>
                              <th className={`border border-gray-300 px-4 py-2 text-center font-medium text-gray-700 ${bgColor}`}>Amount</th>
                            </React.Fragment>
                          );
                        })}
                      </tr>
                    </thead>
                    <tbody>
                      {roomsData[Object.keys(roomsData)[0]]?.map((_, monthIndex) => {
                        const roomTypes = Object.keys(roomsData);
                        return (
                          <tr key={monthIndex} className="hover:bg-yellow-50 transition-colors">
                            <td className="border border-gray-300 px-4 py-3 font-medium text-gray-900 sticky left-0 z-10 bg-white shadow-[2px_0_5px_-2px_rgba(0,0,0,0.1)]">
                              {roomsData[roomTypes[0]][monthIndex].month}
                            </td>
                            {roomTypes.map((roomType, roomIndex) => {
                              const item = roomsData[roomType][monthIndex];
                              const bgColor = roomIndex % 2 === 0 ? 'bg-purple-50' : 'bg-pink-50';
                              const hoverColor = roomIndex % 2 === 0 ? 'hover:bg-purple-100' : 'hover:bg-pink-100';
                              return (
                                <React.Fragment key={`${roomType}-${monthIndex}`}>
                                  <td
                                    className={`border border-gray-300 px-4 py-3 text-center cursor-pointer ${bgColor} ${hoverColor}`}
                                    onClick={() => handleRoomClick(roomType)}
                                  >
                                    <span className={`font-bold ${item.occ >= 50 ? 'text-green-700' : item.occ >= 30 ? 'text-yellow-700' : 'text-red-700'}`}>
                                      {formatPercent(item.occ)}
                                    </span>
                                  </td>
                                  <td
                                    className={`border border-gray-300 px-4 py-3 text-right text-gray-900 cursor-pointer ${bgColor} ${hoverColor}`}
                                    onClick={() => handleRoomClick(roomType)}
                                  >
                                    <div className="space-y-1 text-xs">
                                      <div><span className="text-gray-600">Sales:</span> {formatCurrency(item.totalSales)}</div>
                                      <div><span className="text-gray-600">ADR:</span> {formatCurrency(item.adr)}</div>
                                      <div><span className="text-gray-600">RevPAR:</span> {formatCurrency(item.revpar)}</div>
                                      <div><span className="text-gray-600">Lead Time:</span> {item.avgLeadTime.toFixed(1)}</div>
                                      <div><span className="text-gray-600">Cleaning:</span> {formatCurrency(item.cleaningFee)}</div>
                                      <div><span className="text-gray-600">Commission:</span> {formatCurrency(item.commissions)}</div>
                                    </div>
                                  </td>
                                </React.Fragment>
                              );
                            })}
                          </tr>
                        );
                      })}
                    </tbody>
                  </table>
                </div>
              )}
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
