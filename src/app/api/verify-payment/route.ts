import { NextRequest, NextResponse } from 'next/server';
import { db } from '@/db';
import { settings, payments } from '@/db/schema';
import crypto from 'crypto';
import { eq } from 'drizzle-orm';

export async function POST(req: NextRequest) {
  try {
    const body = await req.json();
    const { razorpay_order_id, razorpay_payment_id, razorpay_signature, userId } = body;

    const settingsData = await db.select().from(settings).limit(1);
    if (settingsData.length === 0) {
      return NextResponse.json({ error: 'Site settings not configured' }, { status: 500 });
    }
    const siteSettings = settingsData[0];

    const hmac = crypto.createHmac('sha256', siteSettings.razorpayKeySecret || '');
    hmac.update(razorpay_order_id + '|' + razorpay_payment_id);
    const generated_signature = hmac.digest('hex');

    if (generated_signature === razorpay_signature) {
      await db.update(payments)
        .set({
          status: 'success',
          paymentId: razorpay_payment_id,
        })
        .where(eq(payments.id, userId));

      return NextResponse.json({ success: true });
    } else {
      await db.update(payments)
        .set({ status: 'failed' })
        .where(eq(payments.id, userId));
      return NextResponse.json({ error: 'Invalid signature' }, { status: 400 });
    }
  } catch (error: any) {
    console.error('Payment Verification Error:', error);
    return NextResponse.json({ error: error.message || 'Failed to verify payment' }, { status: 500 });
  }
}
