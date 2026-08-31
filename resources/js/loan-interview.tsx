import React from 'react';
import { createRoot } from 'react-dom/client';
import { LoanInterviewSession } from './components/LoanInterviewSession';

const rootElement = document.getElementById('interview-root');

if (rootElement) {
  const interviewData = JSON.parse(rootElement.dataset.interview || '{}');

  const root = createRoot(rootElement);
  root.render(
    <React.StrictMode>
      <LoanInterviewSession candidateData={interviewData} />
    </React.StrictMode>
  );
}
