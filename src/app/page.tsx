"use client";
import React, { useState, useEffect } from 'react';

export default function LandingPage() {
  const [settings, setSettings] = useState<any>(null);
  const [formData, setFormData] = useState({
    name: '',
    email: '',
    mobile: '',
  });
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    fetch('/api/admin/settings')
      .then(res => res.json())
      .then(data => setSettings(data));
  }, []);

  const handlePayment = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);
    try {
      const res = await fetch('/api/create-order', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(formData),
      });
      const data = await res.json();

      if (data.orderId) {
        const options = {
          key: settings.razorpayKeyId,
          amount: data.amount,
          currency: 'INR',
          name: settings.logoText,
          description: 'Payment for access',
          order_id: data.orderId,
          handler: async function (response: any) {
            const verifyRes = await fetch('/api/verify-payment', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({
                razorpay_order_id: response.razorpay_order_id,
                razorpay_payment_id: response.razorpay_payment_id,
                razorpay_signature: response.razorpay_signature,
                userId: data.userId,
              }),
            });
            if (verifyRes.ok) {
              window.location.href = '/success';
            } else {
              alert('Payment verification failed!');
            }
          },
          prefill: {
            name: formData.name,
            email: formData.email,
            contact: formData.mobile,
          },
          theme: {
            color: '#2563eb',
          },
        };

        const rzp = new (window as any).Razorpay(options);
        rzp.open();
      }
    } catch (err) {
      console.error('Payment error:', err);
      alert('Something went wrong. Please try again.');
    } finally {
      setLoading(false);
    }
  };

  if (!settings) return <div className="text-center py-10">Loading...</div>;

  return (
    <div className="min-h-screen bg-gray-50 flex flex-col items-center justify-center p-4">
      <div className="max-w-md w-full bg-white rounded-2xl shadow-xl p-8">
        <div className="text-center mb-8">
          {settings.logo && <img src={settings.logo} alt="Logo" className="h-16 mx-auto mb-4" />}
          <h1 className="text-3xl font-bold text-gray-800">{settings.logoText}</h1>
          <p className="text-gray-500 mt-2">Pay ₹{settings.amount} to unlock the exclusive video</p>
        </div>

        <form onSubmit={handlePayment} className="space-y-4">
          <div>
            <label className="block text-sm font-medium text-gray-700">Full Name</label>
            <input 
              type="text" 
              className="w-full p-3 border rounded-lg mt-1 focus:ring-2 focus:ring-blue-500 outline-none" 
              value={formData.name} 
              onChange={e => setFormData({...formData, name: e.target.value})}
              required 
            />
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700">Email Address</label>
            <input 
              type="email" 
              className="w-full p-3 border rounded-lg mt-1 focus:ring-2 focus:ring-blue-500 outline-none" 
              value={formData.email} 
              onChange={e => setFormData({...formData, email: e.target.value})}
              required 
            />
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700">Mobile Number</label>
            <input 
              type="tel" 
              className="w-full p-3 border rounded-lg mt-1 focus:ring-2 focus:ring-blue-500 outline-none" 
              value={formData.mobile} 
              onChange={e => setFormData({...formData, mobile: e.target.value})}
              required 
            />
          </div>
          <button 
            type="submit" 
            disabled={loading}
            className="w-full bg-blue-600 text-white py-3 rounded-lg font-semibold hover:bg-blue-700 transition-colors disabled:bg-blue-300"
          >
            {loading ? 'Processing...' : `Pay ₹${settings.amount} Now`}
          </button>
        </form>
      </div>

    </div>
  );
}
