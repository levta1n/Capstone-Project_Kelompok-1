import { mkdir, writeFile } from 'node:fs/promises'
import path from 'node:path'
import process from 'node:process'

function safePathSegment(value) {
  return value
    .normalize('NFKD')
    .replace(/[^\w.-]+/g, '-')
    .replace(/^-+|-+$/g, '')
    .toLowerCase() || 'lainnya'
}

function escapeHtml(value) {
  return String(value)
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#39;')
}

function relativeAssetPath(reportDirectory, assetPath) {
  if (!assetPath || typeof assetPath !== 'string') return null
  return path.relative(reportDirectory, assetPath).split(path.sep).join('/')
}

function getTestError(test) {
  const attempt = test.attempts?.at(-1)
  return test.displayError
    || attempt?.error?.message
    || test.err?.message
    || ''
}

function getTestState(test) {
  return test.state || test.attempts?.at(-1)?.state || 'unknown'
}

function renderReport(report) {
  const rows = report.tests.map((test) => {
    const state = escapeHtml(test.state)
    const title = escapeHtml(test.title)
    const error = test.error
      ? `<pre>${escapeHtml(test.error)}</pre>`
      : ''

    return `<tr><td><span class="state ${state}">${state}</span></td><td>${title}${error}</td></tr>`
  }).join('')
  const screenshots = report.media.screenshots.map((screenshot) => (
    `<a class="capture" href="${escapeHtml(screenshot.href)}"><img src="${escapeHtml(screenshot.href)}" alt="Tangkapan layar ${escapeHtml(screenshot.name)}"><span>${escapeHtml(screenshot.name)}</span></a>`
  )).join('')
  const video = report.media.video
    ? `<p><a href="${escapeHtml(report.media.video)}">Buka video hasil pengujian</a></p>`
    : '<p>Video tidak tersedia untuk spec ini.</p>'
  const summary = report.summary

  return `<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Hasil pengujian: ${escapeHtml(report.spec)}</title>
  <style>
    :root{font:16px/1.5 system-ui,sans-serif;color:#172033;background:#f4f6fa}
    body{max-width:1100px;margin:2rem auto;padding:0 1rem}
    main{background:#fff;border:1px solid #dce2ec;border-radius:12px;padding:1.5rem}
    h1{font-size:1.5rem;overflow-wrap:anywhere}
    .summary{display:flex;flex-wrap:wrap;gap:.75rem;margin:1.5rem 0}
    .summary div{background:#f4f6fa;padding:.75rem 1rem;border-radius:8px}
    table{width:100%;border-collapse:collapse}
    th,td{text-align:left;vertical-align:top;padding:.75rem;border-bottom:1px solid #e4e8ef}
    .state{font-weight:700;text-transform:capitalize}
    .passed{color:#16803c}.failed{color:#c62828}.pending,.skipped{color:#9a6700}
    pre{white-space:pre-wrap;overflow-wrap:anywhere;background:#fff2f0;padding:.75rem;border-radius:6px}
    .captures{display:flex;flex-wrap:wrap;gap:1rem}
    .capture{width:min(280px,100%);color:inherit;text-decoration:none}
    .capture img{display:block;width:100%;border:1px solid #dce2ec;border-radius:6px}
    .capture span{display:block;margin-top:.25rem;overflow-wrap:anywhere}
  </style>
</head>
<body>
  <main>
    <h1>Hasil pengujian</h1>
    <p><strong>Spec:</strong> ${escapeHtml(report.spec)}<br><strong>Dibuat:</strong> ${escapeHtml(report.generatedAt)}</p>
    <section class="summary" aria-label="Ringkasan">
      <div>Total: <strong>${summary.tests ?? report.tests.length}</strong></div>
      <div>Lulus: <strong>${summary.passes ?? 0}</strong></div>
      <div>Gagal: <strong>${summary.failures ?? 0}</strong></div>
      <div>Durasi: <strong>${summary.duration ?? 0} ms</strong></div>
    </section>
    <h2>Kasus pengujian</h2>
    <table><thead><tr><th>Status</th><th>Kasus / detail kegagalan</th></tr></thead><tbody>${rows}</tbody></table>
    <h2>Media</h2>
    ${video}
    <div class="captures">${screenshots || '<p>Tidak ada tangkapan layar untuk spec ini.</p>'}</div>
  </main>
</body>
</html>`
}

export async function writeCypressReports(results) {
  if (!results?.runs?.length) return

  const projectRoot = process.cwd()
  const reportsRoot = path.join(projectRoot, 'cypress', 'artifacts', 'reports')

  for (const run of results.runs) {
    const spec = run.spec?.relative || run.spec?.name || 'unknown.cy.js'
    const specParts = spec.split(/[\\/]/)
    const e2eIndex = specParts.lastIndexOf('e2e')
    const firstSpecPart = specParts[e2eIndex + 1]
    const functionality = safePathSegment(e2eIndex < 0
      ? 'lainnya'
      : firstSpecPart?.includes('.')
        ? firstSpecPart.replace(/\.(cy|spec)\.[^.]+$/, '')
        : firstSpecPart || 'lainnya')
    const specName = safePathSegment(specParts.at(-1).replace(/\.[^.]+$/, ''))
    const reportDirectory = path.join(reportsRoot, functionality)
    const reportBase = path.join(reportDirectory, specName)
    const htmlPath = `${reportBase}.html`
    const summary = run.stats || {}
    const report = {
      generatedAt: new Date().toISOString(),
      spec,
      summary,
      tests: (run.tests || []).map((test) => ({
        title: Array.isArray(test.title) ? test.title.join(' › ') : test.title || '',
        state: getTestState(test),
        error: getTestError(test),
        attempts: test.attempts || [],
      })),
      media: {
        video: relativeAssetPath(reportDirectory, run.video),
        screenshots: (run.screenshots || []).map((screenshot) => ({
          name: screenshot.name || path.basename(screenshot.path || ''),
          path: relativeAssetPath(reportDirectory, screenshot.path),
          href: relativeAssetPath(reportDirectory, screenshot.path),
        })).filter((screenshot) => screenshot.path),
      },
    }

    await mkdir(reportDirectory, { recursive: true })
    await Promise.all([
      writeFile(`${reportBase}.json`, `${JSON.stringify(report, null, 2)}\n`),
      writeFile(htmlPath, renderReport(report)),
    ])
  }
}
