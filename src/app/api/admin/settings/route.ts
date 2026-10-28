import { NextRequest, NextResponse } from 'next/server';
import { db } from '@/db';
import { settings } from '@/db/schema';
import { eq } from 'drizzle-orm';

export async function GET() {
  try {
    const result = await db.select().from(settings).limit(1);
    return NextResponse.json(result[0] || {});
  } catch (error) {
    return NextResponse.json({ error: 'Failed to fetch settings' }, { status: 500 });
  }
}

export async function POST(req: NextRequest) {
  try {
    const body = await req.json();
    const { logo, logoText, razorpayKeyId, razorpayKeySecret, youtubeLink, amount } = body;

    const existing = await db.select().from(settings).limit(1);
    if (existing.length > 0) {
      await db.update(settings)
        .set({
          logo,
          logoText,
          razorpayKeyId,
          razorpayKeySecret,
          youtubeLink,
          amount: amount as any,
        })
        .where(eq(settings.id, existing[0].id));
    } else {
      await db.insert(settings).values({
        logo,
        logoText,
        razorpayKeyId,
        razorpayKeySecret,
        youtubeLink,
        amount: amount as any,
      });
    }

    return NextResponse.json({ success: true });
  } catch (error) {
    console.error(error);
    return NextResponse.json({ error: 'Failed to update settings' }, { status: 500 });
  }
}
