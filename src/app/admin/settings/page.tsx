"use client";
import React, { useState, useEffect } from 'react';

export default function SettingsPage() {
  const [formData, setFormData] = useState({
    logo: '',
    logoText: '',
    razorpayKeyId: '',
    razorpayKeySecret: '',
    youtubeLink: '',
    amount: '',
  });
  const [loading, setLoading] = useState(false);
  const [message, setMessage] = useState('');

  useEffect(() => {
    fetch('/api/admin/settings')
      .then(res => res.json())
      .then(data => {
        if (data) {
          setFormData({
            logo: data.logo || '',
            logoText: data.logoText || '',
            razorpayKeyId: data.razorpayKeyId || '',
            razorpayKeySecret: data.razorpayKeySecret || '',
            youtubeLink: data.youtubeLink || '',
            amount: data.amount || '',
          });
        }
      });
  }, []);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);
    setMessage('');
    try {
      const res = await fetch('/api/admin/settings', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(formData),
      });
      if (res.ok) {
        setMessage('Settings updated successfully!');
      } else {
        setMessage('Failed to update settings.');
      }
    } catch (err) {
      setMessage('An error occurred.');
    }
    setLoading(false);
  };

  return (
    <div className="max-w-2xl mx-auto bg-white p-8 rounded-lg shadow">
      <h1 className="text-2xl font-bold mb-6">Site Settings</h1>
      {message && <div className={`mb-4 p-3 rounded ${message.includes('success') ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'}`}>{message}</div>}
      <form onSubmit={handleSubmit} className="space-y-4">
        <div>
          <label className="block text-sm font-medium text-gray-700">Logo URL</label>
          <input 
            type="text" 
            className="w-full p-2 border rounded mt-1" 
            value={formData.logo} 
            onChange={e => setFormData({...formData, logo: e.target.value})}
            required 
          />
        </div>
        <div>
          <label className="block text-sm font-medium text-gray-700">Logo Text</label>
          <input 
            type="text" 
            className="w-full p-2 border rounded mt-1" 
            value={formData.logoText} 
            onChange={e => setFormData({...formData, logoText: e.target.value})}
            required 
          />
        </div>
        <div className="grid grid-cols-2 gap-4">
          <div>
            <label className="block text-sm font-medium text-gray-700">Razorpay Key ID</label>
            <input 
              type="text" 
              className="w-full p-2 border rounded mt-1" 
              value={formData.razorpayKeyId} 
              onChange={e => setFormData({...formData, razorpayKeyId: e.target.value})}
              required 
            />
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700">Razorpay Key Secret</label>
            <input 
              type="password" 
              className="w-full p-2 border rounded mt-1" 
              value={formData.razorpayKeySecret} 
              onChange={e => setFormData({...formData, razorpayKeySecret: e.target.value})}
              required 
            />
          </div>
        </div>
        <div>
          <label className="block text-sm font-medium text-gray-700">YouTube Video Link (Unlisted)</label>
          <input 
            type="text" 
            className="w-full p-2 border rounded mt-1" 
            value={formData.youtubeLink} 
            onChange={e => setFormData({...formData, youtubeLink: e.target.value})}
            required 
          />
        </div>
        <div>
          <label className="block text-sm font-medium text-gray-700">Amount (INR)</label>
          <input 
            type="number" 
            step="0.01"
            className="w-full p-2 border rounded mt-1" 
            value={formData.amount} 
            onChange={e => setFormData({...formData, amount: e.target.value})}
            required 
          />
        </div>
        <button 
          type="submit" 
          disabled={loading}
          className="w-full bg-blue-600 text-white py-2 rounded hover:bg-blue-700 disabled:bg-blue-300"
        >
          {loading ? 'Saving...' : 'Save Settings'}
        </button>
      </form>
    </div>
  );
}
