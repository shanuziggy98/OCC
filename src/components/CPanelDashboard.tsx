'use client';

import React, { useState, useEffect, useCallback } from 'react';
import { useRouter } from 'next/navigation';
import {
  Building2,
  Users,
  Plus,
  Pencil,
  Trash2,
  Save,
  X,
  Settings,
  RefreshCw,
  UserPlus,
  Shield
} from 'lucide-react';

interface Property {
  id?: number;
  property_name: string;
  property_type: 'guesthouse' | 'hostel';
  total_rooms: number;
  commission_rate: number;
  cleaning_fee: number;
  has_180_day_limit: boolean;
  room_types?: string[];
  owner_username?: string;
  google_sheet_url?: string;
  commission_method?: string;
  display_order?: number;
}

interface User {
  id?: number;
  username: string;
  password?: string;
  user_type: 'admin' | 'owner' | 'cpanel' | string;
  property_name: string;
  full_name: string;
  email: string;
  owner_id?: string;
}


export default function CPanelDashboard() {
  const router = useRouter();
  const [authenticated, setAuthenticated] = useState(false);
  const [activeTab, setActiveTab] = useState<'properties' | 'users' | 'settings'>('properties');
  const [properties, setProperties] = useState<Property[]>([]);
  const [users, setUsers] = useState<User[]>([]);
  const [loading, setLoading] = useState(true);
  const [message, setMessage] = useState<{ type: 'success' | 'error'; text: string } | null>(null);

  // Property form state
  const [showPropertyForm, setShowPropertyForm] = useState(false);
  const [editingProperty, setEditingProperty] = useState<Property | null>(null);
  const [propertyForm, setPropertyForm] = useState<Property>({
    property_name: '',
    property_type: 'guesthouse',
    total_rooms: 1,
    commission_rate: 15,
    cleaning_fee: 5000,
    has_180_day_limit: false,
    room_types: [],
    owner_username: '',
    google_sheet_url: '',
    display_order: 0
  });
  const [newRoomType, setNewRoomType] = useState('');

  // User form state
  const [showUserForm, setShowUserForm] = useState(false);
  const [editingUser, setEditingUser] = useState<User | null>(null);
  const [userForm, setUserForm] = useState<User>({
    username: '',
    password: '',
    user_type: 'owner',
    property_name: '',
    full_name: '',
    email: '',
    owner_id: ''
  });

  // Owner creation state (for creating owner with property)
  const [createNewOwner, setCreateNewOwner] = useState(false);
  const [newOwnerForm, setNewOwnerForm] = useState({
    username: '',
    password: '',
    full_name: '',
    email: ''
  });

  const phpApiUrl = 'https://exseed.main.jp/WG/analysis/OCC/cpanel_api.php';
  const authApiUrl = 'https://exseed.main.jp/WG/analysis/OCC/auth_api.php';

  const checkAuth = useCallback(async () => {
    try {
      const response = await fetch(`${authApiUrl}?action=check`, {
        credentials: 'include',
      });
      const data = await response.json();

      if (!data.authenticated) {
        router.push('/login');
        return;
      }

      if (data.user.user_type !== 'cpanel') {
        // Redirect based on user type
        if (data.user.user_type === 'admin') {
          router.push('/admin-dashboard');
        } else {
          router.push('/property-dashboard');
        }
        return;
      }

      setAuthenticated(true);
      fetchData();
    } catch (err) {
      console.error('Auth check failed:', err);
      router.push('/login');
    }
  }, [router]);

  const fetchData = async () => {
    setLoading(true);
    try {
      // Fetch properties
      const propsResponse = await fetch(`${phpApiUrl}?action=get_properties`, {
        credentials: 'include',
      });
      const propsData = await propsResponse.json();
      console.log('Properties response:', propsData);

      if (propsData.error) {
        setMessage({ type: 'error', text: propsData.error });
        setLoading(false);
        return;
      }

      if (propsData.properties) {
        setProperties(propsData.properties);
      }

      // Fetch users
      const usersResponse = await fetch(`${phpApiUrl}?action=get_users`, {
        credentials: 'include',
      });
      const usersData = await usersResponse.json();
      console.log('Users response:', usersData);

      if (usersData.error) {
        setMessage({ type: 'error', text: usersData.error });
        setLoading(false);
        return;
      }

      if (usersData.users) {
        setUsers(usersData.users);
      }
    } catch (error) {
      console.error('Error fetching data:', error);
      setMessage({ type: 'error', text: 'Failed to fetch data: ' + (error as Error).message });
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    checkAuth();
  }, [checkAuth]);

  const handleLogout = async () => {
    try {
      await fetch(`${authApiUrl}?action=logout`, {
        credentials: 'include',
      });
      sessionStorage.removeItem('user');
      router.push('/login');
    } catch (err) {
      console.error('Logout failed:', err);
    }
  };

  // Property management functions
  const handleAddProperty = () => {
    setEditingProperty(null);
    setPropertyForm({
      property_name: '',
      property_type: 'guesthouse',
      total_rooms: 1,
      commission_rate: 15,
      cleaning_fee: 5000,
      has_180_day_limit: false,
      room_types: [],
      owner_username: '',
      google_sheet_url: '',
      display_order: 0
    });
    setCreateNewOwner(false);
    setNewOwnerForm({
      username: '',
      password: '',
      full_name: '',
      email: ''
    });
    setShowPropertyForm(true);
  };

  const handleEditProperty = (property: Property) => {
    setEditingProperty(property);
    setPropertyForm({
      ...property,
      room_types: property.room_types || [],
      google_sheet_url: property.google_sheet_url || '',
      display_order: property.display_order || 0
    });
    setCreateNewOwner(false);
    setNewOwnerForm({
      username: '',
      password: '',
      full_name: '',
      email: ''
    });
    setShowPropertyForm(true);
  };

  const handleSaveProperty = async () => {
    try {
      // If creating new owner with property, first create the owner
      if (createNewOwner && !editingProperty) {
        if (!newOwnerForm.username || !newOwnerForm.password) {
          setMessage({ type: 'error', text: 'Owner username and password are required' });
          return;
        }

        // Create the owner user with the property_name
        const ownerResponse = await fetch(`${phpApiUrl}?action=add_user`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          credentials: 'include',
          body: JSON.stringify({
            username: newOwnerForm.username,
            password: newOwnerForm.password,
            user_type: 'owner',
            property_name: propertyForm.property_name,
            full_name: newOwnerForm.full_name,
            email: newOwnerForm.email
          }),
        });

        const ownerData = await ownerResponse.json();

        if (!ownerData.success) {
          setMessage({ type: 'error', text: ownerData.error || 'Failed to create owner' });
          return;
        }

        // Set the owner_username to the newly created owner
        propertyForm.owner_username = newOwnerForm.username;
      }

      const action = editingProperty ? 'update_property' : 'add_property';
      const response = await fetch(`${phpApiUrl}?action=${action}`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        credentials: 'include',
        body: JSON.stringify({
          ...propertyForm,
          id: editingProperty?.id,
          create_owner: createNewOwner && !editingProperty,
          new_owner: createNewOwner ? newOwnerForm : null
        }),
      });

      const data = await response.json();

      if (data.success) {
        setMessage({ type: 'success', text: editingProperty ? 'Property updated successfully' : 'Property added successfully' });
        setShowPropertyForm(false);
        fetchData();
      } else {
        setMessage({ type: 'error', text: data.error || 'Operation failed' });
      }
    } catch (error) {
      console.error('Error saving property:', error);
      setMessage({ type: 'error', text: 'Failed to save property' });
    }
  };

  const handleDeleteProperty = async (property: Property) => {
    if (!confirm(`Are you sure you want to delete ${property.property_name}? This will also delete all associated data.`)) {
      return;
    }

    try {
      const response = await fetch(`${phpApiUrl}?action=delete_property`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        credentials: 'include',
        body: JSON.stringify({ id: property.id, property_name: property.property_name }),
      });

      const data = await response.json();

      if (data.success) {
        setMessage({ type: 'success', text: 'Property deleted successfully' });
        fetchData();
      } else {
        setMessage({ type: 'error', text: data.error || 'Delete failed' });
      }
    } catch (error) {
      console.error('Error deleting property:', error);
      setMessage({ type: 'error', text: 'Failed to delete property' });
    }
  };

  const addRoomType = () => {
    if (newRoomType.trim() && !propertyForm.room_types?.includes(newRoomType.trim())) {
      setPropertyForm({
        ...propertyForm,
        room_types: [...(propertyForm.room_types || []), newRoomType.trim()]
      });
      setNewRoomType('');
    }
  };

  const removeRoomType = (roomType: string) => {
    setPropertyForm({
      ...propertyForm,
      room_types: propertyForm.room_types?.filter(rt => rt !== roomType) || []
    });
  };

  // User management functions
  const handleAddUser = () => {
    setEditingUser(null);
    setUserForm({
      username: '',
      password: '',
      user_type: 'owner',
      property_name: '',
      full_name: '',
      email: '',
      owner_id: ''
    });
    setShowUserForm(true);
  };

  const handleEditUser = (user: User) => {
    setEditingUser(user);
    setUserForm({
      ...user,
      password: '', // Don't show existing password
      owner_id: user.owner_id || ''
    });
    setShowUserForm(true);
  };

  const handleSaveUser = async () => {
    try {
      const action = editingUser ? 'update_user' : 'add_user';
      const response = await fetch(`${phpApiUrl}?action=${action}`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        credentials: 'include',
        body: JSON.stringify({
          ...userForm,
          id: editingUser?.id,
          owner_id: userForm.user_type === 'owner' ? userForm.owner_id : null
        }),
      });

      const data = await response.json();

      if (data.success) {
        setMessage({ type: 'success', text: editingUser ? 'User updated successfully' : 'User added successfully' });
        setShowUserForm(false);
        fetchData();
      } else {
        setMessage({ type: 'error', text: data.error || 'Operation failed' });
      }
    } catch (error) {
      console.error('Error saving user:', error);
      setMessage({ type: 'error', text: 'Failed to save user' });
    }
  };

  const handleDeleteUser = async (user: User) => {
    if (!confirm(`Are you sure you want to delete user ${user.username}?`)) {
      return;
    }

    try {
      const response = await fetch(`${phpApiUrl}?action=delete_user`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        credentials: 'include',
        body: JSON.stringify({ id: user.id }),
      });

      const data = await response.json();

      if (data.success) {
        setMessage({ type: 'success', text: 'User deleted successfully' });
        fetchData();
      } else {
        setMessage({ type: 'error', text: data.error || 'Delete failed' });
      }
    } catch (error) {
      console.error('Error deleting user:', error);
      setMessage({ type: 'error', text: 'Failed to delete user' });
    }
  };

  const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('ja-JP', { style: 'currency', currency: 'JPY' }).format(amount);
  };

  if (!authenticated) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-gray-50">
        <div className="text-center">
          <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-purple-600 mx-auto"></div>
          <p className="mt-4 text-gray-600">Verifying authentication...</p>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-gray-50">
      {/* Header */}
      <div className="bg-purple-700 text-white p-6 shadow-lg">
        <div className="max-w-7xl mx-auto flex items-center justify-between">
          <div className="flex items-center gap-3">
            <div className="bg-white/20 p-2 rounded-lg">
              <Shield className="h-6 w-6" />
            </div>
            <div>
              <h1 className="text-2xl font-bold">C-Panel Dashboard</h1>
              <p className="text-sm text-purple-200">Property Management System Control Panel</p>
            </div>
          </div>
          <button
            onClick={handleLogout}
            className="px-4 py-2 bg-white/20 hover:bg-white/30 rounded-lg transition-colors"
          >
            Logout
          </button>
        </div>
      </div>

      {/* Navigation Tabs */}
      <div className="bg-white border-b">
        <div className="max-w-7xl mx-auto px-6">
          <div className="flex gap-4">
            <button
              onClick={() => setActiveTab('properties')}
              className={`flex items-center gap-2 px-4 py-4 font-medium border-b-2 transition-colors ${
                activeTab === 'properties'
                  ? 'border-purple-600 text-purple-600'
                  : 'border-transparent text-gray-600 hover:text-gray-900'
              }`}
            >
              <Building2 className="h-5 w-5" />
              Properties
            </button>
            <button
              onClick={() => setActiveTab('users')}
              className={`flex items-center gap-2 px-4 py-4 font-medium border-b-2 transition-colors ${
                activeTab === 'users'
                  ? 'border-purple-600 text-purple-600'
                  : 'border-transparent text-gray-600 hover:text-gray-900'
              }`}
            >
              <Users className="h-5 w-5" />
              Users
            </button>
            <button
              onClick={() => setActiveTab('settings')}
              className={`flex items-center gap-2 px-4 py-4 font-medium border-b-2 transition-colors ${
                activeTab === 'settings'
                  ? 'border-purple-600 text-purple-600'
                  : 'border-transparent text-gray-600 hover:text-gray-900'
              }`}
            >
              <Settings className="h-5 w-5" />
              Settings
            </button>
          </div>
        </div>
      </div>

      {/* Message */}
      {message && (
        <div className={`max-w-7xl mx-auto px-6 mt-4`}>
          <div className={`p-4 rounded-lg ${
            message.type === 'success' ? 'bg-green-50 text-green-800 border border-green-200' : 'bg-red-50 text-red-800 border border-red-200'
          }`}>
            <div className="flex items-center justify-between">
              <span>{message.text}</span>
              <button onClick={() => setMessage(null)} className="text-gray-500 hover:text-gray-700">
                <X className="h-4 w-4" />
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Content */}
      <div className="max-w-7xl mx-auto p-6">
        {loading ? (
          <div className="flex items-center justify-center h-64">
            <RefreshCw className="h-8 w-8 animate-spin text-purple-600" />
          </div>
        ) : (
          <>
            {/* Properties Tab */}
            {activeTab === 'properties' && (
              <div>
                <div className="flex items-center justify-between mb-6">
                  <h2 className="text-xl font-bold text-gray-900">Property Management</h2>
                  <button
                    onClick={handleAddProperty}
                    className="flex items-center gap-2 px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors"
                  >
                    <Plus className="h-4 w-4" />
                    Add Property
                  </button>
                </div>

                <div className="bg-white rounded-lg shadow overflow-hidden">
                  <table className="w-full">
                    <thead className="bg-gray-50">
                      <tr>
                        <th className="px-4 py-3 text-left text-sm font-medium text-gray-700">Property Name</th>
                        <th className="px-4 py-3 text-left text-sm font-medium text-gray-700">Type</th>
                        <th className="px-4 py-3 text-center text-sm font-medium text-gray-700">Rooms</th>
                        <th className="px-4 py-3 text-right text-sm font-medium text-gray-700">Commission %</th>
                        <th className="px-4 py-3 text-right text-sm font-medium text-gray-700">Cleaning Fee</th>
                        <th className="px-4 py-3 text-center text-sm font-medium text-gray-700">180-Day Limit</th>
                        <th className="px-4 py-3 text-left text-sm font-medium text-gray-700">Owner</th>
                        <th className="px-4 py-3 text-center text-sm font-medium text-gray-700">Order</th>
                        <th className="px-4 py-3 text-center text-sm font-medium text-gray-700">Actions</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-200">
                      {properties.length === 0 ? (
                        <tr>
                          <td colSpan={9} className="px-4 py-8 text-center text-gray-500">
                            No properties found. Click &quot;Add Property&quot; to create one.
                          </td>
                        </tr>
                      ) : (
                        properties.map((property) => (
                          <tr key={property.id || property.property_name} className="hover:bg-gray-50">
                            <td className="px-4 py-3 font-medium text-gray-900">{property.property_name}</td>
                            <td className="px-4 py-3">
                              <span className={`px-2 py-1 rounded text-xs font-medium ${
                                property.property_type === 'hostel'
                                  ? 'bg-blue-100 text-blue-700'
                                  : 'bg-green-100 text-green-700'
                              }`}>
                                {property.property_type}
                              </span>
                            </td>
                            <td className="px-4 py-3 text-center text-gray-900">{property.total_rooms}</td>
                            <td className="px-4 py-3 text-right text-gray-900">{property.commission_rate}%</td>
                            <td className="px-4 py-3 text-right text-gray-900">{formatCurrency(property.cleaning_fee)}</td>
                            <td className="px-4 py-3 text-center">
                              {property.has_180_day_limit ? (
                                <span className="text-yellow-600 font-medium">Yes</span>
                              ) : (
                                <span className="text-gray-400">No</span>
                              )}
                            </td>
                            <td className="px-4 py-3 text-gray-600">{property.owner_username || '-'}</td>
                            <td className="px-4 py-3 text-center text-gray-600">{property.display_order || 0}</td>
                            <td className="px-4 py-3">
                              <div className="flex items-center justify-center gap-2">
                                <button
                                  onClick={() => handleEditProperty(property)}
                                  className="p-1 text-blue-600 hover:bg-blue-50 rounded"
                                  title="Edit"
                                >
                                  <Pencil className="h-4 w-4" />
                                </button>
                                <button
                                  onClick={() => handleDeleteProperty(property)}
                                  className="p-1 text-red-600 hover:bg-red-50 rounded"
                                  title="Delete"
                                >
                                  <Trash2 className="h-4 w-4" />
                                </button>
                              </div>
                            </td>
                          </tr>
                        ))
                      )}
                    </tbody>
                  </table>
                </div>
              </div>
            )}

            {/* Users Tab */}
            {activeTab === 'users' && (
              <div>
                <div className="flex items-center justify-between mb-6">
                  <h2 className="text-xl font-bold text-gray-900">User Management</h2>
                  <button
                    onClick={handleAddUser}
                    className="flex items-center gap-2 px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors"
                  >
                    <UserPlus className="h-4 w-4" />
                    Add User
                  </button>
                </div>

                <div className="bg-white rounded-lg shadow overflow-hidden">
                  <table className="w-full">
                    <thead className="bg-gray-50">
                      <tr>
                        <th className="px-4 py-3 text-center text-sm font-medium text-gray-700">ID</th>
                        <th className="px-4 py-3 text-left text-sm font-medium text-gray-700">Username</th>
                        <th className="px-4 py-3 text-left text-sm font-medium text-gray-700">Password</th>
                        <th className="px-4 py-3 text-left text-sm font-medium text-gray-700">Full Name</th>
                        <th className="px-4 py-3 text-left text-sm font-medium text-gray-700">Email</th>
                        <th className="px-4 py-3 text-left text-sm font-medium text-gray-700">Type</th>
                        <th className="px-4 py-3 text-left text-sm font-medium text-gray-700">Owner ID</th>
                        <th className="px-4 py-3 text-left text-sm font-medium text-gray-700">Property</th>
                        <th className="px-4 py-3 text-center text-sm font-medium text-gray-700">Actions</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-200">
                      {users.length === 0 ? (
                        <tr>
                          <td colSpan={9} className="px-4 py-8 text-center text-gray-500">
                            No users found. Click &quot;Add User&quot; to create one.
                          </td>
                        </tr>
                      ) : (
                        users.map((user) => (
                          <tr key={user.id || user.username} className="hover:bg-gray-50">
                            <td className="px-4 py-3 text-center text-gray-500">{user.id}</td>
                            <td className="px-4 py-3 font-medium text-gray-900">{user.username}</td>
                            <td className="px-4 py-3 text-gray-600 font-mono text-sm">{user.password || '••••••'}</td>
                            <td className="px-4 py-3 text-gray-900">{user.full_name}</td>
                            <td className="px-4 py-3 text-gray-600">{user.email}</td>
                            <td className="px-4 py-3">
                              <span className={`px-2 py-1 rounded text-xs font-medium ${
                                user.user_type === 'admin'
                                  ? 'bg-red-100 text-red-700'
                                  : user.user_type === 'cpanel'
                                  ? 'bg-purple-100 text-purple-700'
                                  : 'bg-blue-100 text-blue-700'
                              }`}>
                                {user.user_type}
                              </span>
                            </td>
                            <td className="px-4 py-3 text-gray-600">{user.owner_id || '-'}</td>
                            <td className="px-4 py-3 text-gray-600">{user.property_name || '-'}</td>
                            <td className="px-4 py-3">
                              <div className="flex items-center justify-center gap-2">
                                <button
                                  onClick={() => handleEditUser(user)}
                                  className="p-1 text-blue-600 hover:bg-blue-50 rounded"
                                  title="Edit"
                                >
                                  <Pencil className="h-4 w-4" />
                                </button>
                                <button
                                  onClick={() => handleDeleteUser(user)}
                                  className="p-1 text-red-600 hover:bg-red-50 rounded"
                                  title="Delete"
                                >
                                  <Trash2 className="h-4 w-4" />
                                </button>
                              </div>
                            </td>
                          </tr>
                        ))
                      )}
                    </tbody>
                  </table>
                </div>
              </div>
            )}

            {/* Settings Tab */}
            {activeTab === 'settings' && (
              <div>
                <h2 className="text-xl font-bold text-gray-900 mb-6">System Settings</h2>

                <div className="bg-white rounded-lg shadow p-6">
                  <div className="space-y-6">
                    <div className="border-b pb-4">
                      <h3 className="font-semibold text-gray-900 mb-2">Database Information</h3>
                      <p className="text-sm text-gray-600">
                        All changes made in this C-Panel are automatically saved to the database and will be reflected in the Admin Dashboard and Property Owner dashboards.
                      </p>
                    </div>

                    <div className="border-b pb-4">
                      <h3 className="font-semibold text-gray-900 mb-2">API Endpoints</h3>
                      <div className="text-sm text-gray-600 space-y-1">
                        <p><strong>C-Panel API:</strong> {phpApiUrl}</p>
                        <p><strong>Auth API:</strong> {authApiUrl}</p>
                      </div>
                    </div>

                    <div>
                      <h3 className="font-semibold text-gray-900 mb-2">Quick Actions</h3>
                      <div className="flex gap-4">
                        <button
                          onClick={fetchData}
                          className="flex items-center gap-2 px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors"
                        >
                          <RefreshCw className="h-4 w-4" />
                          Refresh Data
                        </button>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            )}
          </>
        )}
      </div>

      {/* Property Form Modal */}
      {showPropertyForm && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
          <div className="bg-white rounded-lg shadow-xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
            <div className="flex items-center justify-between p-4 border-b">
              <h3 className="text-lg font-semibold text-gray-900">
                {editingProperty ? 'Edit Property' : 'Add New Property'}
              </h3>
              <button
                onClick={() => setShowPropertyForm(false)}
                className="text-gray-500 hover:text-gray-700"
              >
                <X className="h-5 w-5" />
              </button>
            </div>

            <div className="p-4 space-y-4">
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">Property Name *</label>
                  <input
                    type="text"
                    value={propertyForm.property_name}
                    onChange={(e) => setPropertyForm({ ...propertyForm, property_name: e.target.value })}
                    className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent text-gray-900"
                    placeholder="e.g., sakura_house"
                    required
                  />
                </div>

                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">Property Type *</label>
                  <select
                    value={propertyForm.property_type}
                    onChange={(e) => setPropertyForm({
                      ...propertyForm,
                      property_type: e.target.value as 'guesthouse' | 'hostel',
                      room_types: e.target.value === 'guesthouse' ? [] : propertyForm.room_types
                    })}
                    className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent text-gray-900"
                  >
                    <option value="guesthouse">Guesthouse</option>
                    <option value="hostel">Hostel (Multiple Rooms)</option>
                  </select>
                </div>
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Google Sheet URL *</label>
                <input
                  type="url"
                  value={propertyForm.google_sheet_url || ''}
                  onChange={(e) => setPropertyForm({ ...propertyForm, google_sheet_url: e.target.value })}
                  className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent text-gray-900"
                  placeholder="https://docs.google.com/spreadsheets/d/SHEET_ID/export?format=csv&gid=0"
                  required={!editingProperty}
                />
                <p className="text-xs text-gray-500 mt-1">
                  CSV export URL from Google Sheets. The table will be auto-created to store booking data.
                </p>
              </div>

              <div className="grid grid-cols-3 gap-4">
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">Total Rooms *</label>
                  <input
                    type="number"
                    min="1"
                    value={propertyForm.total_rooms}
                    onChange={(e) => setPropertyForm({ ...propertyForm, total_rooms: parseInt(e.target.value) || 1 })}
                    className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent text-gray-900"
                  />
                </div>

                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">Commission Rate (%)</label>
                  <input
                    type="number"
                    min="0"
                    max="100"
                    value={propertyForm.commission_rate}
                    onChange={(e) => setPropertyForm({ ...propertyForm, commission_rate: parseFloat(e.target.value) || 0 })}
                    className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent text-gray-900"
                  />
                </div>

                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">Cleaning Fee (¥)</label>
                  <input
                    type="number"
                    min="0"
                    value={propertyForm.cleaning_fee}
                    onChange={(e) => setPropertyForm({ ...propertyForm, cleaning_fee: parseFloat(e.target.value) || 0 })}
                    className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent text-gray-900"
                  />
                </div>
              </div>

              <div className="grid grid-cols-2 gap-4">
                <div className="flex items-center">
                  <label className="flex items-center gap-2 cursor-pointer">
                    <input
                      type="checkbox"
                      checked={propertyForm.has_180_day_limit}
                      onChange={(e) => setPropertyForm({ ...propertyForm, has_180_day_limit: e.target.checked })}
                      className="w-4 h-4 text-purple-600 border-gray-300 rounded focus:ring-purple-500"
                    />
                    <span className="text-sm font-medium text-gray-700">Has 180-Day Limit</span>
                  </label>
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">Display Order</label>
                  <input
                    type="number"
                    min="0"
                    value={propertyForm.display_order || 0}
                    onChange={(e) => setPropertyForm({ ...propertyForm, display_order: parseInt(e.target.value) || 0 })}
                    className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent text-gray-900"
                    placeholder="0"
                  />
                </div>
              </div>

              {/* Owner Assignment Section */}
              <div className="border-t pt-4 mt-4">
                <label className="block text-sm font-medium text-gray-700 mb-3">Property Owner</label>

                {!editingProperty && (
                  <div className="flex items-center gap-4 mb-3">
                    <label className="flex items-center gap-2 cursor-pointer">
                      <input
                        type="radio"
                        checked={!createNewOwner}
                        onChange={() => setCreateNewOwner(false)}
                        className="w-4 h-4 text-purple-600"
                      />
                      <span className="text-sm text-gray-700">Select Existing Owner</span>
                    </label>
                    <label className="flex items-center gap-2 cursor-pointer">
                      <input
                        type="radio"
                        checked={createNewOwner}
                        onChange={() => setCreateNewOwner(true)}
                        className="w-4 h-4 text-purple-600"
                      />
                      <span className="text-sm text-gray-700">Create New Owner</span>
                    </label>
                  </div>
                )}

                {!createNewOwner ? (
                  <select
                    value={propertyForm.owner_username || ''}
                    onChange={(e) => setPropertyForm({ ...propertyForm, owner_username: e.target.value })}
                    className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent text-gray-900"
                  >
                    <option value="">No owner assigned</option>
                    {/* Get unique owner_ids from owner users */}
                    {Array.from(new Set(users.filter(u => u.user_type === 'owner' && u.owner_id).map(u => u.owner_id))).map(ownerId => {
                      const user = users.find(u => u.owner_id === ownerId && u.user_type === 'owner');
                      return (
                        <option key={ownerId} value={ownerId}>
                          {ownerId} - {user?.full_name || ''}
                        </option>
                      );
                    })}
                  </select>
                ) : (
                  <div className="bg-gray-50 p-4 rounded-lg border space-y-3">
                    <p className="text-xs text-gray-500 mb-2">
                      Create a new owner account that will be linked to this property
                    </p>
                    <div className="grid grid-cols-2 gap-3">
                      <div>
                        <label className="block text-xs font-medium text-gray-700 mb-1">Username *</label>
                        <input
                          type="text"
                          value={newOwnerForm.username}
                          onChange={(e) => setNewOwnerForm({ ...newOwnerForm, username: e.target.value })}
                          className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent text-gray-900 text-sm"
                          placeholder="owner_username"
                        />
                      </div>
                      <div>
                        <label className="block text-xs font-medium text-gray-700 mb-1">Password *</label>
                        <input
                          type="password"
                          value={newOwnerForm.password}
                          onChange={(e) => setNewOwnerForm({ ...newOwnerForm, password: e.target.value })}
                          className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent text-gray-900 text-sm"
                          placeholder="password"
                        />
                      </div>
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                      <div>
                        <label className="block text-xs font-medium text-gray-700 mb-1">Full Name</label>
                        <input
                          type="text"
                          value={newOwnerForm.full_name}
                          onChange={(e) => setNewOwnerForm({ ...newOwnerForm, full_name: e.target.value })}
                          className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent text-gray-900 text-sm"
                          placeholder="Full Name"
                        />
                      </div>
                      <div>
                        <label className="block text-xs font-medium text-gray-700 mb-1">Email</label>
                        <input
                          type="email"
                          value={newOwnerForm.email}
                          onChange={(e) => setNewOwnerForm({ ...newOwnerForm, email: e.target.value })}
                          className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent text-gray-900 text-sm"
                          placeholder="email@example.com"
                        />
                      </div>
                    </div>
                  </div>
                )}
              </div>

              {/* Room Types for Hostels */}
              {propertyForm.property_type === 'hostel' && (
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">Room Types</label>
                  <div className="flex gap-2 mb-2">
                    <input
                      type="text"
                      value={newRoomType}
                      onChange={(e) => setNewRoomType(e.target.value)}
                      placeholder="e.g., Room A, Dorm, Suite"
                      className="flex-1 px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent text-gray-900"
                      onKeyDown={(e) => e.key === 'Enter' && (e.preventDefault(), addRoomType())}
                    />
                    <button
                      type="button"
                      onClick={addRoomType}
                      className="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200"
                    >
                      Add
                    </button>
                  </div>
                  {propertyForm.room_types && propertyForm.room_types.length > 0 && (
                    <div className="flex flex-wrap gap-2">
                      {propertyForm.room_types.map((rt, index) => (
                        <span
                          key={index}
                          className="flex items-center gap-1 px-3 py-1 bg-purple-100 text-purple-700 rounded-full text-sm"
                        >
                          {rt}
                          <button
                            type="button"
                            onClick={() => removeRoomType(rt)}
                            className="hover:text-purple-900"
                          >
                            <X className="h-3 w-3" />
                          </button>
                        </span>
                      ))}
                    </div>
                  )}
                </div>
              )}
            </div>

            <div className="flex justify-end gap-3 p-4 border-t bg-gray-50">
              <button
                onClick={() => setShowPropertyForm(false)}
                className="px-4 py-2 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200"
              >
                Cancel
              </button>
              <button
                onClick={handleSaveProperty}
                className="flex items-center gap-2 px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700"
              >
                <Save className="h-4 w-4" />
                {editingProperty ? 'Update Property' : 'Add Property'}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* User Form Modal */}
      {showUserForm && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
          <div className="bg-white rounded-lg shadow-xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
            <div className="flex items-center justify-between p-4 border-b">
              <h3 className="text-lg font-semibold text-gray-900">
                {editingUser ? 'Edit User' : 'Add New User'}
              </h3>
              <button
                onClick={() => setShowUserForm(false)}
                className="text-gray-500 hover:text-gray-700"
              >
                <X className="h-5 w-5" />
              </button>
            </div>

            <div className="p-4 space-y-4">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Username *</label>
                <input
                  type="text"
                  value={userForm.username}
                  onChange={(e) => setUserForm({ ...userForm, username: e.target.value })}
                  className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent text-gray-900"
                  placeholder="e.g., john_owner"
                  required
                />
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  Password {editingUser ? '(leave blank to keep current)' : '*'}
                </label>
                <input
                  type="password"
                  value={userForm.password}
                  onChange={(e) => setUserForm({ ...userForm, password: e.target.value })}
                  className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent text-gray-900"
                  placeholder="Enter password"
                  required={!editingUser}
                />
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Full Name *</label>
                <input
                  type="text"
                  value={userForm.full_name}
                  onChange={(e) => setUserForm({ ...userForm, full_name: e.target.value })}
                  className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent text-gray-900"
                  placeholder="e.g., John Smith"
                  required
                />
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Email *</label>
                <input
                  type="email"
                  value={userForm.email}
                  onChange={(e) => setUserForm({ ...userForm, email: e.target.value })}
                  className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent text-gray-900"
                  placeholder="e.g., john@example.com"
                  required
                />
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">User Type *</label>
                <select
                  value={userForm.user_type}
                  onChange={(e) => setUserForm({ ...userForm, user_type: e.target.value as 'admin' | 'owner' | 'cpanel' })}
                  className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent text-gray-900"
                >
                  <option value="owner">Property Owner</option>
                  <option value="admin">Admin</option>
                  <option value="cpanel">C-Panel</option>
                </select>
              </div>

              {userForm.user_type === 'owner' && (
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">Owner ID *</label>
                  <input
                    type="text"
                    value={userForm.owner_id || ''}
                    onChange={(e) => setUserForm({ ...userForm, owner_id: e.target.value })}
                    className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent text-gray-900"
                    placeholder="e.g., owner123"
                    required
                  />
                  <p className="text-xs text-gray-500 mt-1">
                    Unique identifier for this owner (used to link properties)
                  </p>
                </div>
              )}

              {userForm.user_type === 'owner' && (
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">Assigned Property</label>
                  <select
                    value={userForm.property_name}
                    onChange={(e) => setUserForm({ ...userForm, property_name: e.target.value })}
                    className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent text-gray-900"
                  >
                    <option value="">Select a property</option>
                    {properties.map(prop => (
                      <option key={prop.id || prop.property_name} value={prop.property_name}>
                        {prop.property_name}
                      </option>
                    ))}
                  </select>
                </div>
              )}
            </div>

            <div className="flex justify-end gap-3 p-4 border-t bg-gray-50">
              <button
                onClick={() => setShowUserForm(false)}
                className="px-4 py-2 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200"
              >
                Cancel
              </button>
              <button
                onClick={handleSaveUser}
                className="flex items-center gap-2 px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700"
              >
                <Save className="h-4 w-4" />
                {editingUser ? 'Update User' : 'Add User'}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
