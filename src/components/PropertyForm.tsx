'use client';

import { useState } from 'react';
import { Plus } from 'lucide-react';

interface PropertyFormProps {
  onAddProperty: (property: { name: string; type: 'hotel' | 'guesthouse'; totalRooms: number }) => void;
}

export default function PropertyForm({ onAddProperty }: PropertyFormProps) {
  const [name, setName] = useState('');
  const [type, setType] = useState<'hotel' | 'guesthouse'>('hotel');
  const [totalRooms, setTotalRooms] = useState<number>(0);

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (name && totalRooms > 0) {
      onAddProperty({ name, type, totalRooms });
      setName('');
      setTotalRooms(0);
    }
  };

  return (
    <form onSubmit={handleSubmit} className="space-y-4">
      <div>
        <label className="block text-sm font-medium text-gray-700 mb-2">
          Property Name
        </label>
        <input
          type="text"
          value={name}
          onChange={(e) => setName(e.target.value)}
          className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
          placeholder="Enter property name"
          required
        />
      </div>

      <div>
        <label className="block text-sm font-medium text-gray-700 mb-2">
          Property Type
        </label>
        <select
          value={type}
          onChange={(e) => setType(e.target.value as 'hotel' | 'guesthouse')}
          className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
        >
          <option value="hotel">Hotel</option>
          <option value="guesthouse">Guest House</option>
        </select>
      </div>

      <div>
        <label className="block text-sm font-medium text-gray-700 mb-2">
          Total Rooms
        </label>
        <input
          type="number"
          value={totalRooms || ''}
          onChange={(e) => setTotalRooms(parseInt(e.target.value) || 0)}
          className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
          placeholder="Enter total number of rooms"
          min="1"
          required
        />
      </div>

      <button
        type="submit"
        className="w-full bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors flex items-center justify-center gap-2"
      >
        <Plus className="h-4 w-4" />
        Add Property
      </button>
    </form>
  );
}