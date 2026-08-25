import React from 'react';
import { createRoot } from 'react-dom/client';
import { InterviewSession } from './components/InterviewSession';

const rootElement = document.getElementById('interview-root');

if (rootElement) {
  const interviewData = JSON.parse(rootElement.dataset.interview || '{}');

  const root = createRoot(rootElement);
  root.render(
    <React.StrictMode>
      <InterviewSession candidateData={interviewData} />
    </React.StrictMode>
  );
}