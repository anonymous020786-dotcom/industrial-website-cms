// A tiny stand-in for the Telegram Bot API, so the e2e suite can test codes, one-tap approval and
// alerts without a real bot or internet access. The app uses it only in debug mode, when the
// `telegram_api_base` setting points here.
import http from 'node:http';

export const FAKE_TOKEN = '123456789:AAE2eTestTokenForTheFakeTelegramApi';

export function startFakeTelegram(port = 18081) {
  const state = { sent: [], updates: [], nextUpdate: 1, nextMessage: 100, calls: [] };
  const server = http.createServer((req, res) => {
    let body = '';
    req.on('data', (c) => { body += c; });
    req.on('end', () => {
      const m = req.url.match(/^\/bot([^/]+)\/(\w+)$/);
      const reply = (obj, code = 200) => { res.writeHead(code, { 'Content-Type': 'application/json' }); res.end(JSON.stringify(obj)); };
      if (!m) return reply({ ok: false, description: 'Not Found' }, 404);
      const [, token, method] = m;
      if (token !== FAKE_TOKEN) return reply({ ok: false, error_code: 401, description: 'Unauthorized' }, 401);
      let p = {};
      try { p = body ? JSON.parse(body) : {}; } catch { /* ignore */ }
      state.calls.push({ method, params: p });
      switch (method) {
        case 'getMe': return reply({ ok: true, result: { id: 123456789, is_bot: true, first_name: 'E2E Bot', username: 'e2e_test_bot' } });
        case 'sendMessage': {
          const id = state.nextMessage++;
          state.sent.push({ id, chat: String(p.chat_id), text: p.text, buttons: p.reply_markup?.inline_keyboard?.flat() || [] });
          return reply({ ok: true, result: { message_id: id } });
        }
        case 'getUpdates': {
          const out = state.updates; // everything queued (the app keeps its own offset across runs)
          state.updates = [];
          return reply({ ok: true, result: out });
        }
        default: return reply({ ok: true, result: true }); // answerCallbackQuery, editMessageText, setWebhook, deleteWebhook
      }
    });
  });
  return new Promise((resolve) => server.listen(port, '127.0.0.1', () => resolve({
    url: `http://127.0.0.1:${port}`,
    state,
    /** Queue a private message from a chat (as if the user typed it to the bot). */
    message(chatId, text, username = 'e2e_user') {
      state.updates.push({ update_id: state.nextUpdate++, message: { message_id: state.nextMessage++, chat: { id: chatId, type: 'private' }, from: { id: chatId, username }, text } });
    },
    /** Queue a button press. */
    press(chatId, data, messageId = 1) {
      state.updates.push({ update_id: state.nextUpdate++, callback_query: { id: 'cb' + state.nextUpdate, from: { id: chatId }, data, message: { message_id: messageId, chat: { id: chatId } } } });
    },
    lastTo(chatId) { return [...state.sent].reverse().find((s) => s.chat === String(chatId)); },
    close: () => new Promise((r) => server.close(r)),
  })));
}
