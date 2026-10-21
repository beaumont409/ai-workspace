export const API_URL = 'https://status.triangle250r.com/api/status';

const pick = (...values) => values.find(value => value !== undefined && value !== null);
const number = value => (typeof value === 'number' || (typeof value === 'string' && value.trim() !== '')) && Number.isFinite(Number(value)) ? Number(value) : null;
export function percent(value) {
  const n = number(typeof value === 'string' ? value.replace(/%$/, '') : value);
  return n === null || n < 0 || n > 100 ? null : n;
}
export function health(value) {
  if (value === true) return 'healthy';
  if (value === false) return 'down';
  const text = String(value ?? '').toLowerCase();
  if (['ok', 'up', 'online', 'healthy', 'operational', 'running'].includes(text)) return 'healthy';
  if (['degraded', 'warning', 'partial', 'maintenance'].includes(text)) return 'degraded';
  if (['down', 'offline', 'error', 'failed', 'unhealthy', 'critical'].includes(text)) return 'down';
  return 'unknown';
}
function usage(value) {
  if (!value || typeof value !== 'object') return { percent: percent(value), used: null, total: null };
  const used = number(pick(value.used, value.used_bytes));
  const total = number(pick(value.total, value.total_bytes));
  return { percent: percent(pick(value.percent, value.percentage, value.usage_percent, value.usage, used !== null && total > 0 ? used / total * 100 : undefined)), used, total };
}
export function normalizeStatus(payload) {
  if (!payload || typeof payload !== 'object' || Array.isArray(payload)) throw new Error('The status API returned an invalid response.');
  const data = payload.data && typeof payload.data === 'object' ? payload.data : payload;
  const system = data.system ?? data.metrics ?? data;
  const rawServices = data.services;
  const services = Array.isArray(rawServices) ? rawServices.map((s, i) => typeof s === 'object' && s !== null ? { name: String(s.name ?? s.service ?? `Service ${i + 1}`), status: health(pick(s.status, s.healthy, s.online)), description: String(s.description ?? '') } : { name: String(s), status: 'unknown', description: '' }) : rawServices && typeof rawServices === 'object' ? Object.entries(rawServices).map(([name, s]) => ({ name, status: health(typeof s === 'object' && s !== null ? pick(s.status, s.healthy, s.online) : s), description: String(s?.description ?? '') })) : [];
  const cpu = usage(pick(system.cpu, system.cpu_usage, system.cpu_percent));
  const ram = usage(pick(system.ram, system.memory, system.memory_usage, system.ram_usage));
  const disk = usage(pick(system.disk, system.disk_usage));
  const rawUptime = pick(system.uptime, system.uptime_seconds, data.uptime);
  const uptime = typeof rawUptime === 'string' && number(rawUptime) === null ? rawUptime : number(rawUptime);
  const explicit = health(pick(data.status, data.health, data.healthy));
  const overall = services.some(s => s.status === 'down') ? 'down' : services.some(s => s.status === 'degraded') ? 'degraded' : explicit !== 'unknown' ? explicit : services.length && services.every(s => s.status === 'healthy') ? 'healthy' : 'unknown';
  if (!services.length && [cpu.percent, ram.percent, disk.percent, uptime].every(v => v === null) && explicit === 'unknown') throw new Error('The API response contains no recognized status data.');
  return { overall, cpu, ram, disk, uptime, services };
}
export function formatUptime(value) {
  if (value === null || value === undefined || (typeof value === 'number' && value < 0)) return 'Unavailable';
  if (typeof value === 'string') return value;
  const days = Math.floor(value / 86400), hours = Math.floor(value % 86400 / 3600), minutes = Math.floor(value % 3600 / 60);
  return days ? `${days}d ${hours}h` : hours ? `${hours}h ${minutes}m` : `${minutes}m`;
}
export function formatBytes(value) {
  if (value === null || value < 0) return null;
  const unit = value >= 1024 ** 3 ? 3 : value >= 1024 ** 2 ? 2 : value >= 1024 ? 1 : 0;
  return `${(value / 1024 ** unit).toFixed(unit ? 1 : 0)} ${['B', 'KiB', 'MiB', 'GiB'][unit]}`;
}
export async function fetchStatus(fetcher = fetch, timeout = 12000) {
  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), timeout);
  try {
    const response = await fetcher(API_URL, { signal: controller.signal, cache: 'no-store', headers: { Accept: 'application/json' } });
    if (!response.ok) throw new Error(`Status API responded with HTTP ${response.status}.`);
    return normalizeStatus(await response.json());
  } finally { clearTimeout(timer); }
}
