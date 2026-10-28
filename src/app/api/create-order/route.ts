import { NextRequest, NextResponse } from 'next/server';
import { db } from '@/db';
import { settings, payments } from '@/db/schema';
import Razorpay from 'razorpay';

export async function POST(req: NextRequest) {
  try {
    const body = await req.json();
    const { name, email, mobile } = body;

    const settingsData = await db.select().from(settings).limit(1);
    if (settingsData.length === 0) {
      return NextResponse.json({ error: 'Site settings not configured' }, { status: 500 });
    }
    const siteSettings = settingsData[0];

    const razorpay = new Razorpay({
      key_id: siteSettings.razorpayKeyId || '',
      key_secret: siteSettings.razorpayKeySecret || '',
    });

    const amountInPaise = Math.round(Number(siteSettings.amount) * 100);

    const order = await razorpay.orders.create({
      amount: amountInPaise,
      currency: 'INR',
      receipt: `receipt_${Date.now()}`,
    });

    const [payment] = await db.insert(payments).values({
      userName: name,
      userEmail: email,
      userMobile: mobile,
      orderId: order.id,
      amount: siteSettings.amount,
      status: 'pending',
    }).returning();

    return NextResponse.json({
      orderId: order.id,
      amount: amountInPaise,
      userId: payment.id,
    });
  } catch (error: any) {
    console.error('Razorpay Order Error:', error);
    return NextResponse.json({ error: error.message || 'Failed to create order' }, { status: 500 });
  }
}
