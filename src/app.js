import { fetchStatus, formatBytes, formatUptime } from './status.js';

const $ = id => document.getElementById(id);
let busy = false;
let lastSuccess = null;
const labels = { healthy: 'Operational', degraded: 'Degraded', down: 'Unavailable', unknown: 'Unknown' };
function render(data) {
  $('dashboard').dataset.health = data.overall;
  $('health-icon').textContent = { healthy: '✓', degraded: '!', down: '×', unknown: '?' }[data.overall];
  $('health-heading').textContent = { healthy: 'Systems operational', degraded: 'Some systems are degraded', down: 'Service disruption detected', unknown: 'Health status unavailable' }[data.overall];
  $('health-detail').textContent = data.overall === 'healthy' ? 'The API reports healthy systems.' : data.overall === 'unknown' ? 'The API did not provide a recognized health status.' : 'Review the service details below for the latest reported state.';
  $('live-label').textContent = 'LIVE SNAPSHOT';
  for (const key of ['cpu', 'ram', 'disk']) {
    const metric = data[key];
    $(key + '-value').textContent = metric.percent === null ? '—' : `${metric.percent.toFixed(1)}%`;
    const meter = $(key + '-meter');
    meter.firstElementChild.style.width = `${metric.percent ?? 0}%`;
    meter.dataset.level = metric.percent >= 90 ? 'high' : metric.percent >= 75 ? 'moderate' : 'normal';
    if (metric.percent !== null) { meter.setAttribute('aria-valuenow', metric.percent); meter.removeAttribute('aria-valuetext'); }
    else { meter.removeAttribute('aria-valuenow'); meter.setAttribute('aria-valuetext', 'Unavailable'); }
    $(key + '-detail').textContent = metric.used !== null && metric.total !== null ? `${formatBytes(metric.used)} of ${formatBytes(metric.total)} used` : metric.percent === null ? 'Not reported by the API' : `${(100 - metric.percent).toFixed(1)}% available`;
  }
  $('uptime-value').textContent = formatUptime(data.uptime);
  $('service-count').textContent = `${data.services.length} SERVICE${data.services.length === 1 ? '' : 'S'}`;
  $('service-list').replaceChildren();
  if (!data.services.length) { const p = document.createElement('p'); p.className = 'empty'; p.textContent = 'No individual services reported by the API.'; $('service-list').append(p); }
  for (const service of data.services) {
    const row = document.createElement('div'); row.className = 'service-row';
    const name = document.createElement('div'); name.className = 'service-name'; name.textContent = service.name;
    if (service.description) { const description = document.createElement('small'); description.textContent = service.description; name.append(description); }
    const badge = document.createElement('span'); badge.className = `badge ${service.status}`; badge.textContent = labels[service.status];
    row.append(name, badge); $('service-list').append(row);
  }
}
async function refresh() {
  if (busy) return;
  busy = true; $('refresh').disabled = true; $('refresh-label').textContent = 'Refreshing…'; $('refresh-icon').classList.add('spinning'); $('dashboard').setAttribute('aria-busy', 'true');
  try {
    const data = await fetchStatus(); render(data); lastSuccess = new Date(); $('notice').hidden = true;
    $('updated').textContent = `Last updated ${lastSuccess.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' })}`;
  } catch (error) {
    $('notice').hidden = false;
    $('notice').textContent = `${lastSuccess ? 'Refresh failed. Displaying the last successful snapshot. ' : 'Unable to load system status. '}Check your connection and try Refresh. ${error.name === 'AbortError' ? 'The request timed out.' : error instanceof TypeError ? 'The API may be unreachable or may not allow browser requests (CORS).' : error.message}`;
    $('live-label').textContent = lastSuccess ? 'STALE DATA' : 'CONNECTION ERROR';
    if (!lastSuccess) {
      $('dashboard').dataset.health = 'unknown'; $('health-icon').textContent = '?'; $('health-heading').textContent = 'Status currently unavailable'; $('health-detail').textContent = 'A connection error does not indicate a service outage.';
      $('service-list').replaceChildren(Object.assign(document.createElement('p'), { className: 'empty', textContent: 'Service data could not be loaded.' }));
      $('updated').textContent = 'No successful update yet';
      for (const key of ['cpu', 'ram', 'disk']) $(key + '-detail').textContent = 'Data unavailable';
    }
  } finally { busy = false; $('refresh').disabled = false; $('refresh-label').textContent = 'Refresh status'; $('refresh-icon').classList.remove('spinning'); $('dashboard').setAttribute('aria-busy', 'false'); }
}
$('refresh').addEventListener('click', refresh);
setInterval(() => { if (!document.hidden) refresh(); }, 60000);
refresh();
