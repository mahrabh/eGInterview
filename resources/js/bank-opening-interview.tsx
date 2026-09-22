import React from 'react';
import { createRoot } from 'react-dom/client';
import { BankOpeningInterviewSession } from './components/BankOpeningInterviewSession';

const rootElement = document.getElementById('bank-opening-interview-root');

if (rootElement) {
  const interviewData = JSON.parse(rootElement.dataset.interview || '{}');
  if (!interviewData.public_url && rootElement.dataset.token) {
    interviewData.public_url = rootElement.dataset.token;
  }

  const root = createRoot(rootElement);
  root.render(
    <React.StrictMode>
      <BankOpeningInterviewSession candidateData={interviewData} />
    </React.StrictMode>
  );
}
