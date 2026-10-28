"use client";
import React, { useState, useEffect } from 'react';
import Link from 'next/link';

export default function AdminDashboard() {
  const [payments, setPayments] = useState([]);
  const [sortConfig, setSortConfig] = useState({ key: 'createdAt', direction: 'desc' });
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    fetchPayments();
  }, []);

  const fetchPayments = async () => {
    try {
      const res = await fetch('/api/admin/payments');
      const data = await res.json();
      setPayments(data);
    } catch (err) {
      console.error('Error fetching payments:', err);
    } finally {
      setLoading(false);
    }
  };

  const handleSort = (key: string) => {
    let direction = 'asc';
    if (sortConfig.key === key && sortConfig.direction === 'asc') {
      direction = 'desc';
    }
    setSortConfig({ key, direction });
  };

  const sortedPayments = [...payments].sort((a, b) => {
    if (a[sortConfig.key] < b[sortConfig.key]) {
      return sortConfig.direction === 'asc' ? -1 : 1;
    }
    if (a[sortConfig.key] > b[sortConfig.key]) {
      return sortConfig.direction === 'asc' ? 1 : -1;
    }
    return 0;
  });

  if (loading) return <div className="text-center py-10">Loading payments...</div>;

  return (
    <div>
      <h1 className="text-2xl font-bold mb-6">Payments Dashboard</h1>
      <div className="overflow-x-auto bg-white rounded-lg shadow">
        <table className="w-full text-left border-collapse">
          <thead>
            <tr className="bg-slate-100 text-slate-600 uppercase text-xs font-semibold">
              <th className="px-6 py-3 cursor-pointer hover:bg-slate-200" onClick={() => handleSort('userName')}>User Name {sortConfig.key === 'userName' ? (sortConfig.direction === 'asc' ? '↑' : '↓') : ''}</th>
              <th className="px-6 py-3 cursor-pointer hover:bg-slate-200" onClick={() => handleSort('userEmail')}>Email {sortConfig.key === 'userEmail' ? (sortConfig.direction === 'asc' ? '↑' : '↓') : ''}</th>
              <th className="px-6 py-3 cursor-pointer hover:bg-slate-200" onClick={() => handleSort('amount')}>Amount {sortConfig.key === 'amount' ? (sortConfig.direction === 'asc' ? '↑' : '↓') : ''}</th>
              <th className="px-6 py-3 cursor-pointer hover:bg-slate-200" onClick={() => handleSort('status')}>Status {sortConfig.key === 'status' ? (sortConfig.direction === 'asc' ? '↑' : '↓') : ''}</th>
              <th className="px-6 py-3 cursor-pointer hover:bg-slate-200" onClick={() => handleSort('createdAt')}>Date {sortConfig.key === 'createdAt' ? (sortConfig.direction === 'asc' ? '↑' : '↓') : ''}</th>
              <th className="px-6 py-3">Actions</th>
            </tr>
          </thead>
          <tbody>
            {sortedPayments.map((payment: any, index: number) => (
              <tr key={payment.id} className={`${index % 2 === 0 ? 'bg-white' : 'bg-gray-50'} border-b hover:bg-blue-50 transition-colors`}>
                <td className="px-6 py-4">{payment.userName}</td>
                <td className="px-6 py-4">{payment.userEmail}</td>
                <td className="px-6 py-4">₹{payment.amount}</td>
                <td className="px-6 py-4">
                  <span className={`px-2 py-1 rounded-full text-xs font-medium ${payment.status === 'success' ? 'bg-green-100 text-green-700' : payment.status === 'failed' ? 'bg-red-100 text-red-700' : 'bg-yellow-100 text-yellow-700'}`}>
                    {payment.status}
                  </span>
                </td>
                <td className="px-6 py-4">{new Date(payment.createdAt).toLocaleDateString()}</td>
                <td className="px-6 py-4">
                  <Link href={`/admin/payments/${payment.id}`} className="text-blue-600 hover:underline">View</Link>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}
