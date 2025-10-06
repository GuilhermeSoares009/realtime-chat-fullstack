"use client";

import { AuthProvider } from '@/contexts/AuthContext';

const Provider = ({ children }) => {
  return (
    <AuthProvider>
      {children}
    </AuthProvider>
  );
};

export default Provider;