// One-shot DevTools Protocol probe: evaluates computed styles with Edge headless.
// Usage: node tools/computed-check.js <url> [selector1 selector2 ...]
const { spawn } = require('child_process');
const http = require('http');
const fs = require('fs');
const os = require('os');
const path = require('path');

const url = process.argv[2];
const selectors = process.argv.slice(3);
const edge = 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe';
const profile = fs.mkdtempSync(path.join(os.tmpdir(), 'cm-headless-'));
const port = 9333;

function getJson(u) {
	return new Promise((resolve, reject) => {
		http.get(u, (res) => {
			let d = '';
			res.on('data', (c) => (d += c));
			res.on('end', () => resolve(JSON.parse(d)));
		}).on('error', reject);
	});
}

function cdp(ws, id, method, params = {}) {
	return new Promise((resolve, reject) => {
		const onMsg = (ev) => {
			const msg = JSON.parse(ev.data);
			if (msg.id === id) {
				ws.removeEventListener('message', onMsg);
				resolve(msg);
			}
		};
		ws.addEventListener('message', onMsg);
		ws.send(JSON.stringify({ id, method, params }));
	});
}

async function main() {
	const proc = spawn(edge, [
		'--headless=new', '--disable-gpu', '--no-first-run', '--no-default-browser-check',
		`--user-data-dir=${profile}`, `--remote-debugging-port=${port}`, 'about:blank',
	], { stdio: 'ignore' });

	// Wait for the debugging endpoint.
	let targets;
	for (let i = 0; i < 40; i++) {
		try {
			targets = await getJson(`http://127.0.0.1:${port}/json/list`);
			if (targets.length) break;
		} catch (_) {}
		await new Promise((r) => setTimeout(r, 250));
	}
	if (!targets || !targets.length) throw new Error('CDP not reachable');

	const page = targets.find((t) => t.type === 'page');
	const ws = new WebSocket(page.webSocketDebuggerUrl);
	await new Promise((res, rej) => { ws.addEventListener('open', res); ws.addEventListener('error', rej); });

	await cdp(ws, 1, 'Page.enable');
	await cdp(ws, 2, 'Page.navigate', { url });
	await new Promise((r) => setTimeout(r, 500));
	// Wait for load.
	await cdp(ws, 2, 'Runtime.evaluate', { expression: `new Promise(res => { if (document.readyState === 'complete') res(); else window.addEventListener('load', res); })`, awaitPromise: true });
	await new Promise((r) => setTimeout(r, 2500)); // let late theme CSS/scripts settle

	const expr = `(() => {
		const out = { url: location.href, title: document.title, h1: [...document.querySelectorAll('h1')].map(h => h.textContent.trim()), };
		for (const s of ${JSON.stringify(selectors)}) {
			let node = null;
			try { node = document.querySelector(s); } catch (_) {}
			if (node) {
				const cs = getComputedStyle(node);
				out[s] = { display: cs.display, visibility: cs.visibility, width: cs.width, height: cs.height, rect: node.getBoundingClientRect().toJSON() };
			} else {
				out[s] = null;
			}
		}
		return out;
	})()`;

	const res = await cdp(ws, 3, 'Runtime.evaluate', { expression: expr, returnByValue: true });
	console.log(JSON.stringify(res.result.result.value, null, 2));

	ws.close();
	try { proc.kill(); } catch (_) {}
	try { fs.rmSync(profile, { recursive: true, force: true }); } catch (_) {}
}

main().catch((e) => { console.error('ERR', e); process.exit(1); });