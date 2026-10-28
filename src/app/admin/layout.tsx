import React from 'react';
import Link from 'next/link';

export default function AdminLayout({ children }: { children: React.ReactNode }) {
  return (
    <div className="min-h-screen bg-gray-100 flex">
      <aside className="w-64 bg-slate-800 text-white p-6 space-y-6">
        <div className="text-2xl font-bold mb-8">Admin Panel</div>
        <nav className="space-y-2">
          <Link href="/admin" className="block px-4 py-2 rounded hover:bg-slate-700">Dashboard</Link>
          <Link href="/admin/settings" className="block px-4 py-2 rounded hover:bg-slate-700">Settings</Link>
        </nav>
      </aside>
      <main className="flex-1 p-8">
        {children}
      </main>
    </div>
  );
}
