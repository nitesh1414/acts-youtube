import { NextRequest, NextResponse } from 'next/server';
import { db } from '@/db';
import { payments } from '@/db/schema';
import { desc } from 'drizzle-orm';

export async function GET() {
  try {
    const result = await db.select().from(payments).orderBy(desc(payments.createdAt));
    return NextResponse.json(result);
  } catch (error) {
    return NextResponse.json({ error: 'Failed to fetch payments' }, { status: 500 });
  }
}
