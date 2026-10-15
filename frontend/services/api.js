export async function getDashboardData() {
  const response = await fetch('api/dashboard/summary', { credentials: 'include', headers: { Accept: 'application/json' } });
  if (!response.ok) throw new Error('Unable to load dashboard data');
  const payload = await response.json();
  if (!payload.success) throw new Error(payload.error?.message || 'Unable to load dashboard data');
  return payload.data;
}

export async function getSession() {
  const response = await fetch('api/auth/session', { credentials: 'include' });
  if (!response.ok) throw new Error('Unable to load session');
  return (await response.json()).data;
}
