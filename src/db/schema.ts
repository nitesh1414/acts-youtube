import { pgTable, serial, text, integer, timestamp, numeric } from 'drizzle-orm/pg-core';

export const settings = pgTable('settings', {
  id: serial('id').primaryKey(),
  logo: text('logo'),
  logoText: text('logo_text'),
  razorpayKeyId: text('razorpay_key_id'),
  razorpayKeySecret: text('razorpay_key_secret'),
  youtubeLink: text('youtube_link'),
  amount: numeric('amount', { precision: 10, scale: 2 }),
});

export const payments = pgTable('payments', {
  id: serial('id').primaryKey(),
  userName: text('user_name').notNull(),
  userEmail: text('user_email').notNull(),
  userMobile: text('user_mobile').notNull(),
  orderId: text('order_id'),
  paymentId: text('payment_id'),
  amount: numeric('amount', { precision: 10, scale: 2 }),
  status: text('status').default('pending'), // pending, success, failed
  createdAt: timestamp('created_at').defaultNow(),
});
