const http = require('node:http')
const { WebSocketServer } = require('ws')

const PORT = Number(process.env.PORT || 3001)
const SHARED_SECRET = (process.env.PARLWIN_REALTIME_SECRET || '').trim()
const MAX_BODY_BYTES = 1024 * 1024
const AUTH_REQUIRED = !['0', 'false', 'no', 'off'].includes(
  (process.env.PARLWIN_REALTIME_AUTH_REQUIRED || '1').trim().toLowerCase()
)
const AUTH_TIMEOUT_MS = Number(process.env.PARLWIN_REALTIME_AUTH_TIMEOUT_MS || 2500)
const NEXTCLOUD_BASE_URL = (process.env.PARLWIN_NEXTCLOUD_BASE_URL || 'http://nextcloud-nginx:8080').trim().replace(/\/+$/, '')
const NEXTCLOUD_AUTH_URL = (process.env.PARLWIN_NEXTCLOUD_AUTH_URL || '').trim()

const clients = new Set()
const effectiveAuthUrl = NEXTCLOUD_AUTH_URL !== ''
  ? NEXTCLOUD_AUTH_URL
  : (NEXTCLOUD_BASE_URL !== '' ? `${NEXTCLOUD_BASE_URL}/ocs/v2.php/cloud/user?format=json` : '')

function jsonResponse(res, statusCode, payload) {
  res.writeHead(statusCode, { 'Content-Type': 'application/json' })
  res.end(JSON.stringify(payload))
}

function readBody(req) {
  return new Promise((resolve, reject) => {
    let size = 0
    let body = ''
    req.setEncoding('utf8')
    req.on('data', (chunk) => {
      size += Buffer.byteLength(chunk)
      if (size > MAX_BODY_BYTES) {
        reject(new Error('request body too large'))
        req.destroy()
        return
      }
      body += chunk
    })
    req.on('end', () => resolve(body))
    req.on('error', reject)
  })
}

function broadcast(event) {
  const payload = JSON.stringify(event)
  for (const ws of clients) {
    if (ws.readyState === ws.OPEN) {
      ws.send(payload)
    }
  }
}

async function authenticateWebSocketUpgrade(req) {
  if (!AUTH_REQUIRED) {
    return { ok: true, userId: null }
  }

  if (effectiveAuthUrl === '') {
    return { ok: false, statusCode: 503, reason: 'auth_url_missing' }
  }

  const cookie = (req.headers.cookie || '').toString()
  const authorization = (req.headers.authorization || '').toString()
  if (cookie.trim() === '' && authorization.trim() === '') {
    return { ok: false, statusCode: 401, reason: 'missing_credentials' }
  }

  const headers = {
    Accept: 'application/json',
    'OCS-APIRequest': 'true',
    'User-Agent': 'parlwin-realtime-auth/1.0',
  }
  if (cookie.trim() !== '') {
    headers.Cookie = cookie
  }
  if (authorization.trim() !== '') {
    headers.Authorization = authorization
  }

  const controller = new AbortController()
  const timeoutId = setTimeout(() => controller.abort(), AUTH_TIMEOUT_MS)

  try {
    const response = await fetch(effectiveAuthUrl, {
      method: 'GET',
      headers,
      signal: controller.signal,
      redirect: 'manual',
    })

    if (response.status !== 200) {
      return { ok: false, statusCode: 401, reason: `nextcloud_status_${response.status}` }
    }

    const payload = await response.json().catch(() => null)
    const status = payload && payload.ocs && payload.ocs.meta
      ? String(payload.ocs.meta.status || '').toLowerCase()
      : ''
    if (status !== 'ok') {
      return { ok: false, statusCode: 401, reason: 'nextcloud_meta_not_ok' }
    }

    const userId = payload && payload.ocs && payload.ocs.data
      ? (payload.ocs.data.id || null)
      : null
    return { ok: true, userId }
  } catch (error) {
    const reason = error && typeof error.message === 'string' && error.message !== ''
      ? error.message
      : 'auth_request_failed'
    return { ok: false, statusCode: 503, reason }
  } finally {
    clearTimeout(timeoutId)
  }
}

function rejectUpgrade(socket, statusCode, reason) {
  const line = statusCode === 401 ? '401 Unauthorized' : '503 Service Unavailable'
  socket.write(
    `HTTP/1.1 ${line}\r\n` +
    'Connection: close\r\n' +
    'Content-Type: text/plain; charset=utf-8\r\n' +
    `Content-Length: ${Buffer.byteLength(reason)}\r\n` +
    '\r\n' +
    reason
  )
  socket.destroy()
}

const server = http.createServer(async (req, res) => {
  if (req.method === 'GET' && req.url === '/health') {
    jsonResponse(res, 200, {
      ok: true,
      clients: clients.size,
      authRequired: AUTH_REQUIRED,
      authUrlConfigured: effectiveAuthUrl !== '',
    })
    return
  }

  if (req.method === 'POST' && req.url === '/publish') {
    if (SHARED_SECRET !== '') {
      const incoming = (req.headers['x-parlwin-secret'] || '').toString().trim()
      if (incoming !== SHARED_SECRET) {
        jsonResponse(res, 403, { ok: false, error: 'forbidden' })
        return
      }
    }

    try {
      const rawBody = await readBody(req)
      const incomingEvent = JSON.parse(rawBody || '{}')
      if (typeof incomingEvent.type !== 'string' || incomingEvent.type.trim() === '') {
        jsonResponse(res, 400, { ok: false, error: 'type is required' })
        return
      }

      const event = {
        type: incomingEvent.type.trim(),
        payload: typeof incomingEvent.payload === 'object' && incomingEvent.payload !== null
          ? incomingEvent.payload
          : {},
        timestamp: typeof incomingEvent.timestamp === 'string' && incomingEvent.timestamp !== ''
          ? incomingEvent.timestamp
          : new Date().toISOString(),
      }

      broadcast(event)
      jsonResponse(res, 202, { ok: true, clients: clients.size })
    } catch (error) {
      jsonResponse(res, 400, { ok: false, error: error.message })
    }
    return
  }

  jsonResponse(res, 404, { ok: false, error: 'not found' })
})

const wss = new WebSocketServer({ noServer: true })

wss.on('connection', (ws) => {
  ws.isAlive = true
  clients.add(ws)

  ws.send(JSON.stringify({
    type: 'realtime.connected',
    payload: {
      clients: clients.size,
      authenticated: true,
      userId: ws.userId || null,
    },
    timestamp: new Date().toISOString(),
  }))

  ws.on('pong', () => {
    ws.isAlive = true
  })
  ws.on('close', () => {
    clients.delete(ws)
  })
  ws.on('error', () => {
    clients.delete(ws)
  })
})

server.on('upgrade', async (req, socket, head) => {
  const requestUrl = new URL(req.url || '/', 'http://localhost')
  // The mwaeckerlin/nextcloud:nginx reverse-proxy strips `/ws/<appid>` from
  // the path before forwarding, so this service typically sees `/` here.
  // For backwards compatibility we also accept the legacy `/ws` path.
  if (requestUrl.pathname !== '/' && requestUrl.pathname !== '/ws') {
    socket.write('HTTP/1.1 404 Not Found\r\n\r\n')
    socket.destroy()
    return
  }

  const auth = await authenticateWebSocketUpgrade(req)
  if (!auth.ok) {
    // eslint-disable-next-line no-console
    console.warn(`ws auth rejected: ${auth.reason}`)
    rejectUpgrade(socket, auth.statusCode || 401, `websocket auth failed: ${auth.reason}`)
    return
  }

  wss.handleUpgrade(req, socket, head, (ws) => {
    ws.userId = auth.userId || null
    wss.emit('connection', ws, req)
  })
})

setInterval(() => {
  for (const ws of clients) {
    if (ws.isAlive === false) {
      ws.terminate()
      clients.delete(ws)
      continue
    }
    ws.isAlive = false
    ws.ping()
  }
}, 30000)

server.listen(PORT, () => {
  // eslint-disable-next-line no-console
  console.log(`parlwin-realtime-server listening on ${PORT}`)
})
