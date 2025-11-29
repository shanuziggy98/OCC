'use client';

import React, { useState, useEffect, useCallback } from 'react';
import {
  LineChart,
  Line,
  BarChart,
  Bar,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  Legend,
  ResponsiveContainer,
} from 'recharts';

interface MonthlyData {
  month: number;
  booked_nights: number;
  booking_count: number;
  room_revenue: number;
  occ_rate: number;
  adr: number;
  total_people: number;
  available_rooms: number;
}

interface PropertySummary {
  property_name: string;
  property_type: string;
  total_rooms: number;
  available_years: number[];
  last_imported: string;
  has_180_day_limit?: boolean | number;
}

interface YearlyMetrics {
  property_name: string;
  year: number;
  property_type: string;
  monthly_data: { [month: number]: MonthlyData };
}

interface PropertyMetrics {
  property_name?: string;
  room_type?: string;
  booked_nights: number;
  booking_count: number;
  room_revenue: number;
  occ_rate: number;
  adr: number;
}

interface YearTotals {
  total_revenue: number;
  total_bookings: number;
  total_booked_nights: number;
  avg_occ_rate: number;
  avg_adr: number;
  total_people: number;
}

interface YearDifferences {
  revenue_diff: number;
  revenue_diff_percent: number;
  occ_rate_diff: number;
  booking_count_diff: number;
  adr_diff: number;
}

interface YearComparison {
  property_name: string;
  year1: number;
  year2: number;
  year1_data: YearlyMetrics;
  year2_data: YearlyMetrics;
  year1_totals: YearTotals;
  year2_totals: YearTotals;
  differences: YearDifferences;
}

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

interface LimitData {
  current_fiscal_year: string;
  fiscal_year_start: string;
  fiscal_year_end: string;
  properties: Property180Data[];
  property?: Property180Data;
}

interface OccRecordData {
  start_date: string;
  end_date: string;
  dates: string[];
  property_data: {
    property_name: string;
    total_rooms: number;
    daily_occupancy: { [date: string]: number };
  };
  daily_revenue: { [date: string]: number };
  daily_differences: { [date: string]: number };
}

interface AdminPropertiesOwnerViewProps {
  onBack: () => void;
}

const AdminPropertiesOwnerView: React.FC<AdminPropertiesOwnerViewProps> = ({ onBack }) => {
  const [allProperties, setAllProperties] = useState<string[]>([]);
  const [selectedProperty, setSelectedProperty] = useState<string>('');
  const [summary, setSummary] = useState<PropertySummary | null>(null);
  const [selectedYear, setSelectedYear] = useState<number>(new Date().getFullYear());
  const [yearlyMetrics, setYearlyMetrics] = useState<YearlyMetrics | null>(null);
  const [compareYear1, setCompareYear1] = useState<number>(new Date().getFullYear() - 1);
  const [compareYear2, setCompareYear2] = useState<number>(new Date().getFullYear());
  const [comparison, setComparison] = useState<YearComparison | null>(null);
  const [loading, setLoading] = useState(true);
  const [activeTab, setActiveTab] = useState<'overview' | 'yearly' | 'compare' | '180_limit' | 'occ_record'>('overview');
  const [has180DayLimit, setHas180DayLimit] = useState<boolean>(false);
  const [limitData, setLimitData] = useState<LimitData | null>(null);
  const [ytdYear, setYtdYear] = useState<number>(new Date().getFullYear());
  const [ytdYearData, setYtdYearData] = useState<YearlyMetrics | null>(null);
  const [selectedMonth, setSelectedMonth] = useState<number>(new Date().getMonth() + 1);
  const [selectedMonthYear, setSelectedMonthYear] = useState<number>(new Date().getFullYear());
  const [monthlyMetrics, setMonthlyMetrics] = useState<MonthlyData | null>(null);
  const [monthlyRoomsData, setMonthlyRoomsData] = useState<PropertyMetrics[]>([]);

  // OCC Record state
  const [occRecordData, setOccRecordData] = useState<OccRecordData | null>(null);
  const [occStartDate, setOccStartDate] = useState(() => {
    const now = new Date();
    return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-01`;
  });
  const [occEndDate, setOccEndDate] = useState(() => {
    const now = new Date();
    return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
  });

  const phpApiUrl = 'https://exseed.main.jp/WG/analysis/OCC/property_owner_api.php';
  const metricsApiUrl = 'https://exseed.main.jp/WG/analysis/OCC/occupancy_metrics_api.php';

  // Fetch all properties for admin
  const fetchAllProperties = useCallback(async () => {
    try {
      const response = await fetch(
        `${metricsApiUrl}?year=${new Date().getFullYear()}&month=${new Date().getMonth() + 1}`,
        { credentials: 'include' }
      );
      const data = await response.json();

      if (data.properties && data.properties.length > 0) {
        const propertyNames = data.properties.map((p: { property_name: string }) => p.property_name);
        setAllProperties(propertyNames);
        setSelectedProperty(propertyNames[0]);
      }
      setLoading(false);
    } catch (err) {
      console.error('Failed to fetch properties:', err);
      setLoading(false);
    }
  }, [metricsApiUrl]);

  const fetchSummary = useCallback(async () => {
    if (!selectedProperty) return;

    try {
      const response = await fetch(
        `${phpApiUrl}?action=summary&property=${selectedProperty}`,
        { credentials: 'include' }
      );
      const data = await response.json();
      setSummary(data);

      const has180Limit = data.has_180_day_limit === true || data.has_180_day_limit === 1 || data.has_180_day_limit === '1';
      setHas180DayLimit(has180Limit);
    } catch (err) {
      console.error('Failed to fetch summary:', err);
    }
  }, [selectedProperty, phpApiUrl]);

  const fetch180DayLimit = useCallback(async () => {
    if (!selectedProperty || !has180DayLimit) return;

    try {
      const response = await fetch(
        `${metricsApiUrl}?action=180_day_limit&property=${selectedProperty}`,
        { credentials: 'include' }
      );
      const data = await response.json();

      if (data.properties && data.properties.length > 0) {
        const propertyData = data.properties.find(
          (p: Property180Data) => p.property_name.toLowerCase() === selectedProperty.toLowerCase()
        );
        if (propertyData) {
          setLimitData({
            ...data,
            property: propertyData
          });
        }
      }
    } catch (err) {
      console.error('Failed to fetch 180-day limit data:', err);
    }
  }, [selectedProperty, has180DayLimit, metricsApiUrl]);

  const fetchRoomsData = useCallback(async (year: number, month: number) => {
    if (!selectedProperty) return;

    try {
      let action = '';
      const propName = selectedProperty.toLowerCase();

      if (propName === 'iwatoyama') {
        action = 'iwatoyama_rooms';
      } else if (propName === 'goettingen') {
        action = 'goettingen_rooms';
      } else if (propName === 'littlehouse') {
        action = 'littlehouse_rooms';
      } else if (propName === 'kaguya') {
        action = 'kaguya_rooms';
      }

      if (action) {
        const response = await fetch(
          `${metricsApiUrl}?action=${action}&year=${year}&month=${month}`,
          { credentials: 'include' }
        );
        const data = await response.json();
        setMonthlyRoomsData(data.rooms || []);
      }
    } catch (err) {
      console.error('Failed to fetch rooms data:', err);
    }
  }, [selectedProperty, metricsApiUrl]);

  const fetchYtdYearData = useCallback(async () => {
    if (!selectedProperty) return;

    try {
      const response = await fetch(
        `${phpApiUrl}?action=yearly&property=${selectedProperty}&year=${ytdYear}`,
        { credentials: 'include' }
      );
      const data = await response.json();
      setYtdYearData(data);
    } catch (err) {
      console.error('Failed to fetch YTD year data:', err);
    }
  }, [selectedProperty, ytdYear, phpApiUrl]);

  const fetchMonthlyMetrics = useCallback(async () => {
    if (!selectedProperty) return;

    try {
      const response = await fetch(
        `${phpApiUrl}?action=yearly&property=${selectedProperty}&year=${selectedMonthYear}`,
        { credentials: 'include' }
      );
      const data = await response.json();

      if (data && data.monthly_data && data.monthly_data[selectedMonth]) {
        setMonthlyMetrics(data.monthly_data[selectedMonth]);

        if (data.property_type === 'hostel') {
          fetchRoomsData(selectedMonthYear, selectedMonth);
        }
      }
    } catch (err) {
      console.error('Failed to fetch monthly metrics:', err);
    }
  }, [selectedProperty, phpApiUrl, selectedMonth, selectedMonthYear, fetchRoomsData]);

  const fetchYearlyMetrics = useCallback(async () => {
    if (!selectedProperty) return;

    try {
      const response = await fetch(
        `${phpApiUrl}?action=yearly&property=${selectedProperty}&year=${selectedYear}`,
        { credentials: 'include' }
      );
      const data = await response.json();
      setYearlyMetrics(data);
    } catch (err) {
      console.error('Failed to fetch yearly metrics:', err);
    }
  }, [selectedProperty, phpApiUrl, selectedYear]);

  const fetchComparison = useCallback(async () => {
    if (!selectedProperty) return;

    try {
      const response = await fetch(
        `${phpApiUrl}?action=compare&property=${selectedProperty}&year1=${compareYear1}&year2=${compareYear2}`,
        { credentials: 'include' }
      );
      const data = await response.json();
      setComparison(data);
    } catch (err) {
      console.error('Failed to fetch comparison:', err);
    }
  }, [selectedProperty, phpApiUrl, compareYear1, compareYear2]);

  const fetchOccRecord = useCallback(async () => {
    if (!selectedProperty) return;

    try {
      const response = await fetch(
        `${metricsApiUrl}?action=property_daily_occupancy&property=${selectedProperty}&start_date=${occStartDate}&end_date=${occEndDate}`
      );
      const result = await response.json();

      if (result && result.property_data) {
        setOccRecordData(result);
      }
    } catch (error) {
      console.error('Error fetching OCC record data:', error);
    }
  }, [selectedProperty, occStartDate, occEndDate, metricsApiUrl]);

  useEffect(() => {
    fetchAllProperties();
  }, [fetchAllProperties]);

  useEffect(() => {
    if (selectedProperty) {
      fetchSummary();
      fetchMonthlyMetrics();
    }
  }, [selectedProperty, fetchSummary, fetchMonthlyMetrics]);

  useEffect(() => {
    if (selectedProperty && activeTab === 'overview') {
      fetchMonthlyMetrics();
    }
  }, [selectedProperty, selectedMonth, selectedMonthYear, activeTab, fetchMonthlyMetrics]);

  useEffect(() => {
    if (selectedProperty && activeTab === 'yearly') {
      fetchYearlyMetrics();
    }
  }, [selectedProperty, selectedYear, activeTab, fetchYearlyMetrics]);

  useEffect(() => {
    if (selectedProperty && activeTab === 'compare') {
      fetchComparison();
    }
  }, [selectedProperty, compareYear1, compareYear2, activeTab, fetchComparison]);

  useEffect(() => {
    if (selectedProperty && activeTab === '180_limit' && has180DayLimit) {
      fetch180DayLimit();
    }
  }, [selectedProperty, activeTab, has180DayLimit, fetch180DayLimit]);

  useEffect(() => {
    if (selectedProperty && activeTab === 'overview') {
      fetchYtdYearData();
    }
  }, [selectedProperty, ytdYear, activeTab, fetchYtdYearData]);

  useEffect(() => {
    if (selectedProperty && activeTab === 'occ_record') {
      fetchOccRecord();
    }
  }, [selectedProperty, activeTab, occStartDate, occEndDate, fetchOccRecord]);

  const formatCurrency = (value: number) => {
    return `¥${value.toLocaleString('ja-JP', { maximumFractionDigits: 0 })}`;
  };

  const getMonthName = (month: number) => {
    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    return months[month - 1];
  };

  const getOccRateColor = (rate: number) => {
    if (rate >= 80) return '#10b981'; // green
    if (rate >= 60) return '#eab308'; // yellow
    if (rate >= 40) return '#f97316'; // orange
    return '#ef4444'; // red
  };

  const prepareChartData = (metrics: YearlyMetrics | null) => {
    if (!metrics) return [];

    return Object.values(metrics.monthly_data).map((data) => ({
      month: getMonthName(data.month),
      'Occupancy Rate': data.occ_rate,
      Revenue: data.room_revenue,
      Bookings: data.booking_count,
      ADR: data.adr,
    }));
  };

  const prepareComparisonData = (comp: YearComparison | null) => {
    if (!comp) return [];

    const data = [];
    for (let month = 1; month <= 12; month++) {
      const year1Data = comp.year1_data.monthly_data[month];
      const year2Data = comp.year2_data.monthly_data[month];

      data.push({
        month: getMonthName(month),
        [`${comp.year1} Revenue`]: year1Data.room_revenue,
        [`${comp.year2} Revenue`]: year2Data.room_revenue,
        [`${comp.year1} Occ Rate`]: year1Data.occ_rate,
        [`${comp.year2} Occ Rate`]: year2Data.occ_rate,
        [`${comp.year1} ADR`]: year1Data.adr,
        [`${comp.year2} ADR`]: year2Data.adr,
      });
    }
    return data;
  };

  if (loading) {
    return (
      <div style={styles.loadingContainer}>
        <div style={styles.spinner}>Loading...</div>
      </div>
    );
  }

  return (
    <div style={styles.container}>
      {/* Header */}
      <div style={styles.header}>
        <div>
          <h1 style={styles.title}>Properties Owner View (Admin)</h1>
          <p style={styles.subtitle}>View all properties with owner dashboard features</p>
          {allProperties.length > 0 && (
            <div style={{ marginTop: '10px' }}>
              <select
                value={selectedProperty}
                onChange={(e) => setSelectedProperty(e.target.value)}
                style={styles.propertySelector}
              >
                {allProperties.map((prop) => (
                  <option key={prop} value={prop}>
                    {prop}
                  </option>
                ))}
              </select>
              <span style={{ marginLeft: '10px', fontSize: '12px', color: '#718096' }}>
                ({allProperties.length} total properties)
              </span>
            </div>
          )}
        </div>
        <button onClick={onBack} style={styles.backButton}>
          ← Back to Dashboard
        </button>
      </div>

      {/* Tabs */}
      <div style={styles.tabs}>
        <button
          style={{
            ...styles.tab,
            ...(activeTab === 'overview' ? styles.activeTab : {}),
          }}
          onClick={() => setActiveTab('overview')}
        >
          Overview
        </button>
        <button
          style={{
            ...styles.tab,
            ...(activeTab === 'yearly' ? styles.activeTab : {}),
          }}
          onClick={() => setActiveTab('yearly')}
        >
          Yearly Analysis
        </button>
        <button
          style={{
            ...styles.tab,
            ...(activeTab === 'compare' ? styles.activeTab : {}),
          }}
          onClick={() => setActiveTab('compare')}
        >
          Year Comparison
        </button>
        {has180DayLimit && (
          <button
            style={{
              ...styles.tab,
              ...(activeTab === '180_limit' ? styles.activeTab : {}),
            }}
            onClick={() => setActiveTab('180_limit')}
          >
            180-Day Limit
          </button>
        )}
        <button
          style={{
            ...styles.tab,
            ...(activeTab === 'occ_record' ? styles.activeTab : {}),
          }}
          onClick={() => setActiveTab('occ_record')}
        >
          OCC Record
        </button>
      </div>

      {/* Content */}
      <div style={styles.content}>
        {/* Overview Tab */}
        {activeTab === 'overview' && summary && (
          <div>
            <div style={styles.statsGrid}>
              <div style={styles.statCard}>
                <div style={styles.statLabel}>Property Type</div>
                <div style={styles.statValue}>
                  {summary.property_type === 'hostel' ? 'Hostel' : 'Guesthouse'}
                </div>
              </div>
              <div style={styles.statCard}>
                <div style={styles.statLabel}>Total Rooms</div>
                <div style={styles.statValue}>{summary.total_rooms}</div>
              </div>
              <div style={styles.statCard}>
                <div style={styles.statLabel}>Data Available</div>
                <div style={styles.statValue}>{summary.available_years.length} Years</div>
              </div>
              <div style={styles.statCard}>
                <div style={styles.statLabel}>Last Updated</div>
                <div style={styles.statValue}>
                  {summary.last_imported
                    ? new Date(summary.last_imported).toLocaleDateString()
                    : 'Never'}
                </div>
              </div>
            </div>

            {/* Monthly Performance Selector */}
            <div style={styles.infoBox}>
              <h3 style={styles.infoTitle}>Monthly Performance</h3>
              <div style={styles.controls}>
                <label style={styles.controlLabel}>
                  Month:
                  <select
                    value={selectedMonth}
                    onChange={(e) => setSelectedMonth(Number(e.target.value))}
                    style={styles.select}
                  >
                    <option value={1}>January</option>
                    <option value={2}>February</option>
                    <option value={3}>March</option>
                    <option value={4}>April</option>
                    <option value={5}>May</option>
                    <option value={6}>June</option>
                    <option value={7}>July</option>
                    <option value={8}>August</option>
                    <option value={9}>September</option>
                    <option value={10}>October</option>
                    <option value={11}>November</option>
                    <option value={12}>December</option>
                  </select>
                </label>
                <label style={styles.controlLabel}>
                  Year:
                  <select
                    value={selectedMonthYear}
                    onChange={(e) => setSelectedMonthYear(Number(e.target.value))}
                    style={styles.select}
                  >
                    {summary?.available_years.map((year) => (
                      <option key={year} value={year}>
                        {year}
                      </option>
                    ))}
                  </select>
                </label>
              </div>

              {/* Monthly Metrics Cards */}
              {monthlyMetrics && (
                <div style={styles.statsGrid}>
                  <div style={styles.statCard}>
                    <div style={styles.statLabel}>Revenue</div>
                    <div style={{...styles.statValue, color: '#10b981'}}>
                      {formatCurrency(monthlyMetrics.room_revenue)}
                    </div>
                  </div>
                  <div style={styles.statCard}>
                    <div style={styles.statLabel}>Occupancy Rate</div>
                    <div style={{...styles.statValue, color: getOccRateColor(monthlyMetrics.occ_rate)}}>
                      {monthlyMetrics.occ_rate.toFixed(2)}%
                    </div>
                  </div>
                  <div style={styles.statCard}>
                    <div style={styles.statLabel}>ADR</div>
                    <div style={styles.statValue}>
                      {formatCurrency(monthlyMetrics.adr)}
                    </div>
                  </div>
                  <div style={styles.statCard}>
                    <div style={styles.statLabel}>RevPAR</div>
                    <div style={styles.statValue}>
                      {formatCurrency(
                        monthlyMetrics.available_rooms > 0
                          ? monthlyMetrics.room_revenue / monthlyMetrics.available_rooms
                          : 0
                      )}
                    </div>
                  </div>
                  <div style={styles.statCard}>
                    <div style={styles.statLabel}>Bookings</div>
                    <div style={styles.statValue}>
                      {monthlyMetrics.booking_count}
                    </div>
                  </div>
                  <div style={styles.statCard}>
                    <div style={styles.statLabel}>Booked Nights</div>
                    <div style={styles.statValue}>
                      {monthlyMetrics.booked_nights}
                    </div>
                  </div>
                  <div style={styles.statCard}>
                    <div style={styles.statLabel}>Total Guests</div>
                    <div style={styles.statValue}>
                      {monthlyMetrics.total_people}
                    </div>
                  </div>
                  <div style={styles.statCard}>
                    <div style={styles.statLabel}>Available Rooms</div>
                    <div style={styles.statValue}>
                      {monthlyMetrics.available_rooms}
                    </div>
                  </div>
                </div>
              )}
            </div>

            {/* Monthly Room Performance Table (for hostels) */}
            {summary?.property_type === 'hostel' && monthlyRoomsData.length > 0 && (
              <div style={styles.infoBox}>
                <h3 style={styles.infoTitle}>Room Performance - {getMonthName(selectedMonth)} {selectedMonthYear}</h3>
                <div style={styles.tableContainer}>
                  <table style={styles.table}>
                    <thead>
                      <tr>
                        <th style={styles.th}>Room Name</th>
                        <th style={styles.th}>Occupancy Rate</th>
                        <th style={styles.th}>Revenue</th>
                        <th style={styles.th}>ADR</th>
                        <th style={styles.th}>Bookings</th>
                        <th style={styles.th}>Booked Nights</th>
                      </tr>
                    </thead>
                    <tbody>
                      {monthlyRoomsData.map((room, index) => (
                        <tr key={index}>
                          <td style={styles.td}>
                            <strong>{room.room_type || room.property_name}</strong>
                          </td>
                          <td style={styles.td}>
                            <span
                              style={{
                                ...styles.occBadge,
                                backgroundColor: getOccRateColor(room.occ_rate),
                              }}
                            >
                              {room.occ_rate.toFixed(2)}%
                            </span>
                          </td>
                          <td style={styles.td}>{formatCurrency(room.room_revenue)}</td>
                          <td style={styles.td}>{formatCurrency(room.adr)}</td>
                          <td style={styles.td}>{room.booking_count}</td>
                          <td style={styles.td}>{room.booked_nights}</td>
                        </tr>
                      ))}
                      <tr style={{backgroundColor: '#f7fafc', fontWeight: 'bold'}}>
                        <td style={styles.td}>TOTAL</td>
                        <td style={styles.td}>
                          {(
                            monthlyRoomsData.reduce((sum, room) => sum + room.occ_rate, 0) /
                            monthlyRoomsData.length
                          ).toFixed(2)}
                          %
                        </td>
                        <td style={styles.td}>
                          {formatCurrency(
                            monthlyRoomsData.reduce((sum, room) => sum + room.room_revenue, 0)
                          )}
                        </td>
                        <td style={styles.td}>
                          {formatCurrency(
                            monthlyRoomsData.reduce((sum, room) => sum + room.adr, 0) /
                              monthlyRoomsData.length
                          )}
                        </td>
                        <td style={styles.td}>
                          {monthlyRoomsData.reduce((sum, room) => sum + room.booking_count, 0)}
                        </td>
                        <td style={styles.td}>
                          {monthlyRoomsData.reduce((sum, room) => sum + room.booked_nights, 0)}
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
            )}

            {/* Yearly Revenue Total */}
            {ytdYearData && (
              <div style={styles.infoBox}>
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '16px' }}>
                  <h3 style={styles.infoTitle}>Year-to-Date Revenue</h3>
                  <div style={styles.controls}>
                    <label style={styles.controlLabel}>
                      Year:
                      <select
                        value={ytdYear}
                        onChange={(e) => setYtdYear(Number(e.target.value))}
                        style={styles.select}
                      >
                        {summary?.available_years.map((year) => (
                          <option key={year} value={year}>
                            {year}
                          </option>
                        ))}
                      </select>
                    </label>
                  </div>
                </div>
                <div style={styles.statsGrid}>
                  <div style={styles.statCard}>
                    <div style={styles.statLabel}>Total Revenue</div>
                    <div style={{...styles.statValue, color: '#10b981'}}>
                      {formatCurrency(
                        Object.values(ytdYearData.monthly_data).reduce(
                          (sum, month) => sum + month.room_revenue,
                          0
                        )
                      )}
                    </div>
                  </div>
                  <div style={styles.statCard}>
                    <div style={styles.statLabel}>Total Bookings</div>
                    <div style={styles.statValue}>
                      {Object.values(ytdYearData.monthly_data).reduce(
                        (sum, month) => sum + month.booking_count,
                        0
                      )}
                    </div>
                  </div>
                  <div style={styles.statCard}>
                    <div style={styles.statLabel}>Avg Occupancy Rate</div>
                    <div style={{
                      ...styles.statValue,
                      color: getOccRateColor(
                        Object.values(ytdYearData.monthly_data).reduce(
                          (sum, month) => sum + month.occ_rate,
                          0
                        ) / Object.values(ytdYearData.monthly_data).length
                      )
                    }}>
                      {(
                        Object.values(ytdYearData.monthly_data).reduce(
                          (sum, month) => sum + month.occ_rate,
                          0
                        ) / Object.values(ytdYearData.monthly_data).length
                      ).toFixed(2)}
                      %
                    </div>
                  </div>
                  <div style={styles.statCard}>
                    <div style={styles.statLabel}>Booked Nights</div>
                    <div style={styles.statValue}>
                      {Object.values(ytdYearData.monthly_data).reduce(
                        (sum, month) => sum + month.booked_nights,
                        0
                      )}
                    </div>
                  </div>
                  <div style={styles.statCard}>
                    <div style={styles.statLabel}>Total Guests</div>
                    <div style={styles.statValue}>
                      {Object.values(ytdYearData.monthly_data).reduce(
                        (sum, month) => sum + month.total_people,
                        0
                      )}
                    </div>
                  </div>
                  <div style={styles.statCard}>
                    <div style={styles.statLabel}>Avg ADR</div>
                    <div style={styles.statValue}>
                      {formatCurrency(
                        Object.values(ytdYearData.monthly_data).reduce(
                          (sum, month) => sum + month.adr,
                          0
                        ) / Object.values(ytdYearData.monthly_data).length
                      )}
                    </div>
                  </div>
                </div>
              </div>
            )}

            <div style={styles.infoBox}>
              <h3 style={styles.infoTitle}>Available Years</h3>
              <div style={styles.yearList}>
                {summary.available_years.map((year) => (
                  <span key={year} style={styles.yearBadge}>
                    {year}
                  </span>
                ))}
              </div>
            </div>
          </div>
        )}

        {/* Yearly Analysis Tab */}
        {activeTab === 'yearly' && (
          <div>
            <div style={styles.controls}>
              <label style={styles.controlLabel}>
                Select Year:
                <select
                  value={selectedYear}
                  onChange={(e) => setSelectedYear(Number(e.target.value))}
                  style={styles.select}
                >
                  {summary?.available_years.map((year) => (
                    <option key={year} value={year}>
                      {year}
                    </option>
                  ))}
                </select>
              </label>
            </div>

            {yearlyMetrics && (
              <>
                {/* Revenue Chart */}
                <div style={styles.chartContainer}>
                  <h3 style={styles.chartTitle}>Monthly Revenue - {selectedYear}</h3>
                  <ResponsiveContainer width="100%" height={300}>
                    <BarChart data={prepareChartData(yearlyMetrics)}>
                      <CartesianGrid strokeDasharray="3 3" />
                      <XAxis dataKey="month" />
                      <YAxis />
                      <Tooltip formatter={(value) => formatCurrency(Number(value))} />
                      <Legend />
                      <Bar dataKey="Revenue" fill="#667eea" />
                    </BarChart>
                  </ResponsiveContainer>
                </div>

                {/* Occupancy Rate Chart */}
                <div style={styles.chartContainer}>
                  <h3 style={styles.chartTitle}>Occupancy Rate - {selectedYear}</h3>
                  <ResponsiveContainer width="100%" height={300}>
                    <LineChart data={prepareChartData(yearlyMetrics)}>
                      <CartesianGrid strokeDasharray="3 3" />
                      <XAxis dataKey="month" />
                      <YAxis />
                      <Tooltip formatter={(value) => `${Number(value).toFixed(2)}%`} />
                      <Legend />
                      <Line
                        type="monotone"
                        dataKey="Occupancy Rate"
                        stroke="#10b981"
                        strokeWidth={2}
                      />
                    </LineChart>
                  </ResponsiveContainer>
                </div>

                {/* ADR Chart */}
                <div style={styles.chartContainer}>
                  <h3 style={styles.chartTitle}>ADR (Average Daily Rate) - {selectedYear}</h3>
                  <ResponsiveContainer width="100%" height={300}>
                    <LineChart data={prepareChartData(yearlyMetrics)}>
                      <CartesianGrid strokeDasharray="3 3" />
                      <XAxis dataKey="month" />
                      <YAxis />
                      <Tooltip formatter={(value) => formatCurrency(Number(value))} />
                      <Legend />
                      <Line
                        type="monotone"
                        dataKey="ADR"
                        stroke="#f59e0b"
                        strokeWidth={2}
                      />
                    </LineChart>
                  </ResponsiveContainer>
                </div>

                {/* RevPAR Chart */}
                <div style={styles.chartContainer}>
                  <h3 style={styles.chartTitle}>RevPAR (Revenue Per Available Room) - {selectedYear}</h3>
                  <ResponsiveContainer width="100%" height={300}>
                    <BarChart data={prepareChartData(yearlyMetrics).map(d => ({
                      ...d,
                      RevPAR: (d.Revenue / (Object.values(yearlyMetrics.monthly_data).find(m => getMonthName(m.month) === d.month)?.available_rooms || 1))
                    }))}>
                      <CartesianGrid strokeDasharray="3 3" />
                      <XAxis dataKey="month" />
                      <YAxis />
                      <Tooltip formatter={(value) => formatCurrency(Number(value))} />
                      <Legend />
                      <Bar dataKey="RevPAR" fill="#8b5cf6" />
                    </BarChart>
                  </ResponsiveContainer>
                </div>

                {/* Bookings Chart */}
                <div style={styles.chartContainer}>
                  <h3 style={styles.chartTitle}>Bookings Count - {selectedYear}</h3>
                  <ResponsiveContainer width="100%" height={300}>
                    <BarChart data={prepareChartData(yearlyMetrics)}>
                      <CartesianGrid strokeDasharray="3 3" />
                      <XAxis dataKey="month" />
                      <YAxis />
                      <Tooltip />
                      <Legend />
                      <Bar dataKey="Bookings" fill="#3b82f6" />
                    </BarChart>
                  </ResponsiveContainer>
                </div>

                {/* Monthly Table */}
                <div style={styles.tableContainer}>
                  <h3 style={styles.chartTitle}>Monthly Breakdown - {selectedYear}</h3>
                  <table style={styles.table}>
                    <thead>
                      <tr>
                        <th style={styles.th}>Month</th>
                        <th style={styles.th}>Bookings</th>
                        <th style={styles.th}>Revenue</th>
                        <th style={styles.th}>Occ Rate</th>
                        <th style={styles.th}>ADR</th>
                      </tr>
                    </thead>
                    <tbody>
                      {Object.values(yearlyMetrics.monthly_data).map((data) => (
                        <tr key={data.month}>
                          <td style={styles.td}>{getMonthName(data.month)}</td>
                          <td style={styles.td}>{data.booking_count}</td>
                          <td style={styles.td}>{formatCurrency(data.room_revenue)}</td>
                          <td style={styles.td}>{data.occ_rate.toFixed(2)}%</td>
                          <td style={styles.td}>{formatCurrency(data.adr)}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              </>
            )}
          </div>
        )}

        {/* Comparison Tab */}
        {activeTab === 'compare' && (
          <div>
            <div style={styles.controls}>
              <label style={styles.controlLabel}>
                Year 1:
                <select
                  value={compareYear1}
                  onChange={(e) => setCompareYear1(Number(e.target.value))}
                  style={styles.select}
                >
                  {summary?.available_years.map((year) => (
                    <option key={year} value={year}>
                      {year}
                    </option>
                  ))}
                </select>
              </label>
              <label style={styles.controlLabel}>
                Year 2:
                <select
                  value={compareYear2}
                  onChange={(e) => setCompareYear2(Number(e.target.value))}
                  style={styles.select}
                >
                  {summary?.available_years.map((year) => (
                    <option key={year} value={year}>
                      {year}
                    </option>
                  ))}
                </select>
              </label>
            </div>

            {comparison && (
              <>
                {/* Comparison Stats */}
                <div style={styles.statsGrid}>
                  <div style={styles.statCard}>
                    <div style={styles.statLabel}>Revenue Difference</div>
                    <div
                      style={{
                        ...styles.statValue,
                        color: comparison.differences.revenue_diff >= 0 ? '#10b981' : '#ef4444',
                      }}
                    >
                      {formatCurrency(comparison.differences.revenue_diff)}
                      <span style={{ fontSize: '14px', marginLeft: '8px' }}>
                        ({comparison.differences.revenue_diff_percent.toFixed(2)}%)
                      </span>
                    </div>
                  </div>
                  <div style={styles.statCard}>
                    <div style={styles.statLabel}>Occ Rate Difference</div>
                    <div
                      style={{
                        ...styles.statValue,
                        color: comparison.differences.occ_rate_diff >= 0 ? '#10b981' : '#ef4444',
                      }}
                    >
                      {comparison.differences.occ_rate_diff.toFixed(2)}%
                    </div>
                  </div>
                  <div style={styles.statCard}>
                    <div style={styles.statLabel}>Booking Difference</div>
                    <div
                      style={{
                        ...styles.statValue,
                        color:
                          comparison.differences.booking_count_diff >= 0 ? '#10b981' : '#ef4444',
                      }}
                    >
                      {comparison.differences.booking_count_diff > 0 ? '+' : ''}
                      {comparison.differences.booking_count_diff}
                    </div>
                  </div>
                  <div style={styles.statCard}>
                    <div style={styles.statLabel}>ADR Difference</div>
                    <div
                      style={{
                        ...styles.statValue,
                        color: comparison.differences.adr_diff >= 0 ? '#10b981' : '#ef4444',
                      }}
                    >
                      {formatCurrency(comparison.differences.adr_diff)}
                    </div>
                  </div>
                </div>

                {/* Revenue Comparison Chart */}
                <div style={styles.chartContainer}>
                  <h3 style={styles.chartTitle}>
                    Revenue Comparison: {compareYear1} vs {compareYear2}
                  </h3>
                  <ResponsiveContainer width="100%" height={300}>
                    <BarChart data={prepareComparisonData(comparison)}>
                      <CartesianGrid strokeDasharray="3 3" />
                      <XAxis dataKey="month" />
                      <YAxis />
                      <Tooltip formatter={(value) => formatCurrency(Number(value))} />
                      <Legend />
                      <Bar dataKey={`${compareYear1} Revenue`} fill="#667eea" />
                      <Bar dataKey={`${compareYear2} Revenue`} fill="#10b981" />
                    </BarChart>
                  </ResponsiveContainer>
                </div>

                {/* Occupancy Comparison Chart */}
                <div style={styles.chartContainer}>
                  <h3 style={styles.chartTitle}>
                    Occupancy Rate Comparison: {compareYear1} vs {compareYear2}
                  </h3>
                  <ResponsiveContainer width="100%" height={300}>
                    <LineChart data={prepareComparisonData(comparison)}>
                      <CartesianGrid strokeDasharray="3 3" />
                      <XAxis dataKey="month" />
                      <YAxis />
                      <Tooltip formatter={(value) => `${Number(value).toFixed(2)}%`} />
                      <Legend />
                      <Line
                        type="monotone"
                        dataKey={`${compareYear1} Occ Rate`}
                        stroke="#667eea"
                        strokeWidth={2}
                      />
                      <Line
                        type="monotone"
                        dataKey={`${compareYear2} Occ Rate`}
                        stroke="#10b981"
                        strokeWidth={2}
                      />
                    </LineChart>
                  </ResponsiveContainer>
                </div>

                {/* ADR Comparison Chart */}
                <div style={styles.chartContainer}>
                  <h3 style={styles.chartTitle}>
                    ADR Comparison: {compareYear1} vs {compareYear2}
                  </h3>
                  <ResponsiveContainer width="100%" height={300}>
                    <LineChart data={prepareComparisonData(comparison)}>
                      <CartesianGrid strokeDasharray="3 3" />
                      <XAxis dataKey="month" />
                      <YAxis />
                      <Tooltip formatter={(value) => formatCurrency(Number(value))} />
                      <Legend />
                      <Line
                        type="monotone"
                        dataKey={`${compareYear1} ADR`}
                        stroke="#f59e0b"
                        strokeWidth={2}
                      />
                      <Line
                        type="monotone"
                        dataKey={`${compareYear2} ADR`}
                        stroke="#8b5cf6"
                        strokeWidth={2}
                      />
                    </LineChart>
                  </ResponsiveContainer>
                </div>
              </>
            )}
          </div>
        )}

        {/* 180-Day Limit Tab */}
        {activeTab === '180_limit' && has180DayLimit && (
          <div>
            {limitData && limitData.property ? (
              <>
                {/* Fiscal Year Info */}
                <div style={styles.infoBox}>
                  <h3 style={styles.infoTitle}>Japanese Fiscal Year: {limitData.current_fiscal_year}</h3>
                  <p style={{ fontSize: '14px', color: '#4a5568', marginTop: '8px' }}>
                    Period: {limitData.fiscal_year_start} to {limitData.fiscal_year_end}
                  </p>
                </div>

                {/* Status Cards */}
                <div style={styles.statsGrid}>
                  <div style={styles.statCard}>
                    <div style={styles.statLabel}>Booking Limit</div>
                    <div style={styles.statValue}>{limitData.property.limit_days} days</div>
                  </div>
                  <div style={styles.statCard}>
                    <div style={styles.statLabel}>Days Booked</div>
                    <div style={{
                      ...styles.statValue,
                      color: limitData.property.is_over_limit ? '#e53e3e' : '#2d3748'
                    }}>
                      {limitData.property.booked_days} days
                    </div>
                  </div>
                  <div style={styles.statCard}>
                    <div style={styles.statLabel}>Days Remaining</div>
                    <div style={{
                      ...styles.statValue,
                      color: limitData.property.status === 'critical' ? '#e53e3e' :
                             limitData.property.status === 'warning' ? '#d69e2e' : '#10b981'
                    }}>
                      {limitData.property.remaining_days} days
                    </div>
                  </div>
                  <div style={styles.statCard}>
                    <div style={styles.statLabel}>Utilization</div>
                    <div style={{
                      ...styles.statValue,
                      color: limitData.property.utilization_percent >= 100 ? '#e53e3e' :
                             limitData.property.utilization_percent >= 83 ? '#d69e2e' : '#10b981'
                    }}>
                      {limitData.property.utilization_percent.toFixed(1)}%
                    </div>
                  </div>
                </div>

                {/* Progress Bar */}
                <div style={{ ...styles.infoBox, marginTop: '24px' }}>
                  <h3 style={styles.infoTitle}>Booking Progress</h3>
                  <div style={{
                    width: '100%',
                    backgroundColor: '#e2e8f0',
                    borderRadius: '9999px',
                    height: '24px',
                    marginTop: '16px',
                    overflow: 'hidden'
                  }}>
                    <div style={{
                      height: '100%',
                      backgroundColor: limitData.property.status === 'critical' ? '#e53e3e' :
                                      limitData.property.status === 'warning' ? '#d69e2e' : '#10b981',
                      width: `${Math.min(100, limitData.property.utilization_percent)}%`,
                      transition: 'width 0.3s ease',
                      display: 'flex',
                      alignItems: 'center',
                      justifyContent: 'center',
                      color: 'white',
                      fontSize: '12px',
                      fontWeight: '600'
                    }}>
                      {limitData.property.booked_days} / {limitData.property.limit_days}
                    </div>
                  </div>
                  <div style={{
                    display: 'flex',
                    justifyContent: 'space-between',
                    marginTop: '8px',
                    fontSize: '12px',
                    color: '#718096'
                  }}>
                    <span>0 days</span>
                    <span>180 days</span>
                  </div>
                </div>

                {/* Status Information */}
                <div style={{
                  ...styles.infoBox,
                  marginTop: '24px',
                  backgroundColor: limitData.property.status === 'critical' ? '#fed7d7' :
                                   limitData.property.status === 'warning' ? '#fef5e7' : '#d4f4dd'
                }}>
                  <h3 style={{
                    ...styles.infoTitle,
                    color: limitData.property.status === 'critical' ? '#c53030' :
                           limitData.property.status === 'warning' ? '#c05621' : '#276749'
                  }}>
                    Status: {limitData.property.status === 'critical' ? 'CRITICAL' :
                            limitData.property.status === 'warning' ? 'WARNING' : 'SAFE'}
                  </h3>
                  <p style={{
                    fontSize: '14px',
                    marginTop: '8px',
                    color: limitData.property.status === 'critical' ? '#742a2a' :
                           limitData.property.status === 'warning' ? '#744210' : '#22543d'
                  }}>
                    {limitData.property.is_over_limit ? (
                      'You have exceeded the 180-day limit! No more bookings can be accepted for this fiscal year.'
                    ) : limitData.property.status === 'critical' ? (
                      'Less than 30 days remaining. Please monitor your bookings carefully.'
                    ) : limitData.property.status === 'warning' ? (
                      'You have 30-60 days remaining. Consider managing your booking availability.'
                    ) : (
                      'You have more than 60 days remaining. Your property is in good standing.'
                    )}
                  </p>
                </div>

                {/* Information Box */}
                <div style={{ ...styles.infoBox, marginTop: '24px' }}>
                  <h3 style={styles.infoTitle}>About the 180-Day Limit</h3>
                  <p style={{ fontSize: '14px', color: '#4a5568', marginTop: '8px', lineHeight: '1.6' }}>
                    According to Japanese law, certain properties can only accept bookings for a maximum of 180 days
                    per fiscal year (April 1 - March 31). This limit ensures compliance with local regulations.
                    Once you reach 180 days, you cannot accept any more reservations until the next fiscal year begins.
                  </p>
                </div>

                {/* Booking Details */}
                <div style={styles.statsGrid}>
                  <div style={styles.statCard}>
                    <div style={styles.statLabel}>Total Bookings</div>
                    <div style={styles.statValue}>{limitData.property.booking_count}</div>
                  </div>
                  <div style={styles.statCard}>
                    <div style={styles.statLabel}>Average Days per Booking</div>
                    <div style={styles.statValue}>
                      {limitData.property.booking_count > 0 ?
                        (limitData.property.booked_days / limitData.property.booking_count).toFixed(1) : 0}
                    </div>
                  </div>
                </div>
              </>
            ) : (
              <div style={styles.infoBox}>
                <p style={{ fontSize: '14px', color: '#4a5568' }}>
                  Loading 180-day limit data...
                </p>
              </div>
            )}
          </div>
        )}

        {/* OCC Record Tab */}
        {activeTab === 'occ_record' && (
          <div>
            <h2 style={{ fontSize: '24px', fontWeight: 'bold', marginBottom: '24px', color: '#2d3748' }}>
              OCC Record - {selectedProperty}
            </h2>

            {/* Date Range Selector */}
            <div style={{ display: 'flex', gap: '16px', alignItems: 'center', marginBottom: '24px' }}>
              <div>
                <label style={{ display: 'block', fontSize: '14px', fontWeight: '500', marginBottom: '4px', color: '#4a5568' }}>
                  Start Date
                </label>
                <input
                  type="date"
                  value={occStartDate}
                  onChange={(e) => setOccStartDate(e.target.value)}
                  style={{ border: '1px solid #e2e8f0', borderRadius: '6px', padding: '8px 12px', color: '#2d3748' }}
                />
              </div>
              <div>
                <label style={{ display: 'block', fontSize: '14px', fontWeight: '500', marginBottom: '4px', color: '#4a5568' }}>
                  End Date
                </label>
                <input
                  type="date"
                  value={occEndDate}
                  onChange={(e) => setOccEndDate(e.target.value)}
                  style={{ border: '1px solid #e2e8f0', borderRadius: '6px', padding: '8px 12px', color: '#2d3748' }}
                />
              </div>
              <button
                onClick={fetchOccRecord}
                style={{
                  marginTop: '24px',
                  backgroundColor: '#4299e1',
                  color: 'white',
                  padding: '8px 24px',
                  borderRadius: '6px',
                  border: 'none',
                  cursor: 'pointer',
                  fontWeight: '500'
                }}
              >
                Update
              </button>
            </div>

            {occRecordData ? (
              <>
                <p style={{ fontSize: '14px', color: '#718096', marginBottom: '24px' }}>
                  Last updated: {new Date().toLocaleString('en-US', { timeZone: 'Asia/Tokyo' })}
                </p>

                {/* Occupancy Table */}
                <div style={{ overflowX: 'auto', backgroundColor: 'white', borderRadius: '8px', padding: '16px' }}>
                  <table style={{ width: '100%', borderCollapse: 'collapse' }}>
                    <thead>
                      <tr style={{ backgroundColor: '#f7fafc' }}>
                        <th style={{ border: '1px solid #e2e8f0', padding: '12px', textAlign: 'left', position: 'sticky', left: 0, backgroundColor: '#f7fafc', zIndex: 10, color: '#4a5568' }}>
                          Date
                        </th>
                        {occRecordData.dates.map((date) => (
                          <th key={date} style={{ border: '1px solid #e2e8f0', padding: '12px', textAlign: 'center', whiteSpace: 'nowrap', color: '#4a5568' }}>
                            {new Date(date + 'T00:00:00').toLocaleDateString('en-US', { month: 'numeric', day: 'numeric' })}
                            <br />
                            <span style={{ fontSize: '12px', color: '#a0aec0' }}>{date}</span>
                          </th>
                        ))}
                      </tr>
                    </thead>
                    <tbody>
                      {/* Occupancy Rate Row */}
                      <tr style={{ fontWeight: '600' }}>
                        <td style={{ border: '1px solid #e2e8f0', padding: '12px', position: 'sticky', left: 0, backgroundColor: '#e6fffa', zIndex: 10, color: '#2d3748' }}>
                          Occupancy Rate
                        </td>
                        {occRecordData.dates.map((date) => {
                          const rate = occRecordData.property_data.daily_occupancy[date] || 0;
                          const bgColor =
                            rate === 0 ? '#ffffff' :
                            rate < 25 ? '#fed7d7' :
                            rate < 50 ? '#fef5e7' :
                            rate < 75 ? '#c6f6d5' :
                            '#9ae6b4';

                          return (
                            <td
                              key={date}
                              style={{
                                border: '1px solid #e2e8f0',
                                padding: '12px',
                                textAlign: 'center',
                                backgroundColor: bgColor,
                                color: '#2d3748'
                              }}
                            >
                              {rate.toFixed(2)}%
                            </td>
                          );
                        })}
                      </tr>

                      {/* Total Sales Number Row */}
                      <tr style={{ backgroundColor: '#ebf8ff', fontWeight: '600' }}>
                        <td style={{ border: '1px solid #e2e8f0', padding: '12px', position: 'sticky', left: 0, backgroundColor: '#ebf8ff', zIndex: 10, color: '#2d3748' }}>
                          Total Sales Number
                        </td>
                        {occRecordData.dates.map((date) => {
                          const revenue = occRecordData.daily_revenue[date] || 0;
                          return (
                            <td
                              key={date}
                              style={{
                                border: '1px solid #e2e8f0',
                                padding: '8px',
                                textAlign: 'center',
                                fontSize: '12px',
                                color: '#2d3748'
                              }}
                            >
                              {new Intl.NumberFormat('ja-JP', {
                                style: 'currency',
                                currency: 'JPY',
                                maximumFractionDigits: 0
                              }).format(revenue)}
                            </td>
                          );
                        })}
                      </tr>

                      {/* Total Sales Difference Row */}
                      <tr style={{ backgroundColor: '#f0fff4', fontWeight: '600' }}>
                        <td style={{ border: '1px solid #e2e8f0', padding: '12px', position: 'sticky', left: 0, backgroundColor: '#f0fff4', zIndex: 10, color: '#2d3748' }}>
                          Total Sales Difference
                        </td>
                        {occRecordData.dates.map((date, index) => {
                          const difference = occRecordData.daily_differences[date] || 0;
                          const isPositive = difference >= 0;
                          const isFirst = index === 0;

                          return (
                            <td
                              key={date}
                              style={{
                                border: '1px solid #e2e8f0',
                                padding: '8px',
                                textAlign: 'center',
                                fontSize: '12px',
                                color: isFirst ? '#a0aec0' : (isPositive ? '#2f855a' : '#c53030')
                              }}
                            >
                              {isFirst
                                ? '-'
                                : `${isPositive ? '+' : ''}${new Intl.NumberFormat('ja-JP', {
                                    style: 'currency',
                                    currency: 'JPY',
                                    maximumFractionDigits: 0
                                  }).format(difference)}`}
                            </td>
                          );
                        })}
                      </tr>
                    </tbody>
                  </table>
                </div>
              </>
            ) : (
              <div style={styles.infoBox}>
                <p style={{ fontSize: '14px', color: '#4a5568' }}>
                  Select a date range and click Update to view occupancy records.
                </p>
              </div>
            )}
          </div>
        )}
      </div>
    </div>
  );
};

const styles: { [key: string]: React.CSSProperties } = {
  container: {
    minHeight: '100vh',
    backgroundColor: '#f7fafc',
  },
  loadingContainer: {
    minHeight: '100vh',
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
  },
  spinner: {
    fontSize: '18px',
    color: '#667eea',
  },
  header: {
    backgroundColor: 'white',
    padding: '24px',
    boxShadow: '0 1px 3px rgba(0, 0, 0, 0.1)',
    display: 'flex',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  title: {
    fontSize: '24px',
    fontWeight: 'bold',
    color: '#2d3748',
    marginBottom: '4px',
  },
  subtitle: {
    fontSize: '14px',
    color: '#718096',
  },
  backButton: {
    padding: '10px 20px',
    backgroundColor: '#4299e1',
    color: 'white',
    border: 'none',
    borderRadius: '6px',
    cursor: 'pointer',
    fontSize: '14px',
    fontWeight: '600',
  },
  propertySelector: {
    padding: '8px 12px',
    fontSize: '14px',
    fontWeight: '600',
    border: '2px solid #667eea',
    borderRadius: '6px',
    backgroundColor: 'white',
    color: '#667eea',
    cursor: 'pointer',
    outline: 'none',
  },
  tabs: {
    backgroundColor: 'white',
    padding: '0 24px',
    display: 'flex',
    gap: '8px',
    borderBottom: '1px solid #e2e8f0',
  },
  tab: {
    padding: '16px 24px',
    backgroundColor: 'transparent',
    border: 'none',
    borderBottom: '3px solid transparent',
    cursor: 'pointer',
    fontSize: '14px',
    fontWeight: '600',
    color: '#718096',
    transition: 'all 0.2s',
  },
  activeTab: {
    color: '#667eea',
    borderBottom: '3px solid #667eea',
  },
  content: {
    padding: '24px',
    maxWidth: '1400px',
    margin: '0 auto',
  },
  statsGrid: {
    display: 'grid',
    gridTemplateColumns: 'repeat(auto-fit, minmax(250px, 1fr))',
    gap: '20px',
    marginBottom: '24px',
  },
  statCard: {
    backgroundColor: 'white',
    padding: '24px',
    borderRadius: '8px',
    boxShadow: '0 1px 3px rgba(0, 0, 0, 0.1)',
  },
  statLabel: {
    fontSize: '14px',
    color: '#718096',
    marginBottom: '8px',
  },
  statValue: {
    fontSize: '28px',
    fontWeight: 'bold',
    color: '#2d3748',
  },
  infoBox: {
    backgroundColor: 'white',
    padding: '24px',
    borderRadius: '8px',
    boxShadow: '0 1px 3px rgba(0, 0, 0, 0.1)',
    marginBottom: '24px',
  },
  infoTitle: {
    fontSize: '18px',
    fontWeight: '600',
    color: '#2d3748',
    marginBottom: '16px',
  },
  yearList: {
    display: 'flex',
    gap: '12px',
    flexWrap: 'wrap',
  },
  yearBadge: {
    padding: '8px 16px',
    backgroundColor: '#667eea',
    color: 'white',
    borderRadius: '6px',
    fontSize: '14px',
    fontWeight: '600',
  },
  occBadge: {
    padding: '4px 12px',
    color: 'white',
    borderRadius: '12px',
    fontSize: '13px',
    fontWeight: '600',
    display: 'inline-block',
  },
  controls: {
    display: 'flex',
    gap: '20px',
    marginBottom: '24px',
    flexWrap: 'wrap',
  },
  controlLabel: {
    display: 'flex',
    flexDirection: 'column',
    gap: '8px',
    fontSize: '14px',
    fontWeight: '600',
    color: '#2d3748',
  },
  select: {
    padding: '8px 12px',
    fontSize: '14px',
    border: '1px solid #e2e8f0',
    borderRadius: '6px',
    backgroundColor: 'white',
    cursor: 'pointer',
  },
  chartContainer: {
    backgroundColor: 'white',
    padding: '24px',
    borderRadius: '8px',
    boxShadow: '0 1px 3px rgba(0, 0, 0, 0.1)',
    marginBottom: '24px',
  },
  chartTitle: {
    fontSize: '18px',
    fontWeight: '600',
    color: '#2d3748',
    marginBottom: '20px',
  },
  tableContainer: {
    backgroundColor: 'white',
    padding: '24px',
    borderRadius: '8px',
    boxShadow: '0 1px 3px rgba(0, 0, 0, 0.1)',
    overflowX: 'auto',
  },
  table: {
    width: '100%',
    borderCollapse: 'collapse',
  },
  th: {
    padding: '12px',
    textAlign: 'left',
    borderBottom: '2px solid #e2e8f0',
    fontSize: '14px',
    fontWeight: '600',
    color: '#2d3748',
    backgroundColor: '#f7fafc',
  },
  td: {
    padding: '12px',
    borderBottom: '1px solid #e2e8f0',
    fontSize: '14px',
    color: '#2d3748',
  },
};

export default AdminPropertiesOwnerView;
