import React from 'react';
import { settings } from '@/db/schema';
import { db } from '@/db';

export default async function SuccessPage() {
  const settingsData = await db.select().from(settings).limit(1);
  if (settingsData.length === 0) {
    return <div className="text-center py-10">Settings not configured.</div>;
  }
  const siteSettings = settingsData[0];

  // Extract YouTube ID from link
  const extractYoutubeId = (url: string) => {
    const regExp = /^.*(youtu.be\/|v\/|u\/\w\/|embed\/|watch\?v=|\&v=)([^#\&\?]*).*/;
    const match = url.match(regExp);
    return (match && match[2].length === 11) ? match[2] : null;
  };

  const videoId = extractYoutubeId(siteSettings.youtubeLink || '');

  return (
    <div className="min-h-screen bg-gray-100 flex flex-col items-center justify-center p-4">
      <div className="max-w-4xl w-full bg-white rounded-2xl shadow-2xl p-8 text-center">
        <h1 className="text-4xl font-bold text-green-600 mb-4">Payment Successful! 🎉</h1>
        <p className="text-gray-600 mb-8">Thank you for your payment. You now have access to the exclusive video.</p>
        
        {videoId ? (
          <div className="relative pt-17.5714 pb-0 overflow-hidden rounded-xl shadow-lg mb-8" style={{ paddingTop: '56.25%' }}>
            <iframe 
              className="absolute top-0 left-0 w-full h-full" 
              src={`https://www.youtube.com/embed/${videoId}?autoplay=1`} 
              title="YouTube video player" 
              frameBorder="0" 
              allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" 
              allowFullScreen
            ></iframe>
          </div>
        ) : (
          <div className="bg-red-100 text-red-700 p-4 rounded-lg mb-8">
            Invalid YouTube link configured in admin settings.
          </div>
        )}

        <div className="space-y-4">
          <p className="text-lg font-medium text-gray-700">Don't forget to subscribe to our channel for more content!</p>
          <a 
            href={siteSettings.youtubeLink || '#'} 
            target="_blank" 
            rel="noopener noreferrer"
            className="inline-block bg-red-600 text-white px-8 py-3 rounded-full font-bold text-lg hover:bg-red-700 transition-transform hover:scale-105"
          >
            Subscribe Now 🔔
          </a>
        </div>
      </div>
    </div>
  );
}
