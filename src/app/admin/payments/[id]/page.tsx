import React from 'react';
import { payments } from '@/db/schema';
import { db } from '@/db';
import { eq } from 'drizzle-orm';
import { notFound } from 'next/navigation';

export default async function PaymentDetailPage({ params }: { params: { id: string } }) {
  const payment = await db.select().from(payments).where(eq(payments.id, parseInt(params.id))).limit(1);

  if (!payment || payment.length === 0) {
    notFound();
  }

  const data = payment[0];

  return (
    <div className="max-w-2xl mx-auto bg-white p-8 rounded-lg shadow">
      <h1 className="text-2xl font-bold mb-6">Payment Details</h1>
      <div className="space-y-4">
        <div className="flex justify-between border-b py-2">
          <span className="text-gray-500">User Name</span>
          <span className="font-medium">{data.userName}</span>
        </div>
        <div className="flex justify-between border-b py-2">
          <span className="text-gray-500">Email</span>
          <span className="font-medium">{data.userEmail}</span>
        </div>
        <div className="flex justify-between border-b py-2">
          <span className="text-gray-500">Mobile</span>
          <span className="font-medium">{data.userMobile}</span>
        </div>
        <div className="flex justify-between border-b py-2">
          <span className="text-gray-500">Amount</span>
          <span className="font-medium">₹{data.amount}</span>
        </div>
        <div className="flex justify-between border-b py-2">
          <span className="text-gray-500">Order ID</span>
          <span className="font-medium">{data.orderId || 'N/A'}</span>
        </div>
        <div className="flex justify-between border-b py-2">
          <span className="text-gray-500">Payment ID</span>
          <span className="font-medium">{data.paymentId || 'N/A'}</span>
        </div>
        <div className="flex justify-between border-b py-2">
          <span className="text-gray-500">Status</span>
          <span className={`font-medium ${data.status === 'success' ? 'text-green-600' : 'text-red-600'}`}>{data.status}</span>
        </div>
        <div className="flex justify-between border-b py-2">
          <span className="text-gray-500">Date</span>
          <span className="font-medium">{data.createdAt ? new Date(data.createdAt).toLocaleString() : 'N/A'}</span>
        </div>
      </div>
    </div>
  );
}
