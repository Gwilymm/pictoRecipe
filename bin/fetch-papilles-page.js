const fs = require('node:fs');
const puppeteer = require('puppeteer');

const rawUrl = process.argv[2] || '';
let url;

try {
	url = new URL(rawUrl);
} catch {
	console.error('Invalid URL');
	process.exit(2);
}

if (url.protocol !== 'https:' || ![ 'papillesetpupilles.fr', 'www.papillesetpupilles.fr' ].includes(url.hostname)) {
	console.error('Unsupported URL');
	process.exit(2);
}

const executablePath = [
	process.env.PUPPETEER_EXECUTABLE_PATH,
	'/usr/bin/chromium',
	'/usr/bin/chromium-browser'
].find(candidate => candidate && fs.existsSync(candidate));

if (!executablePath) {
	console.error('Chromium executable not found');
	process.exit(3);
}

(async () => {
	const browser = await puppeteer.launch({
		headless: true,
		executablePath,
		args: [ '--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage' ]
	});

	try {
		const page = await browser.newPage();
		await page.setViewport({ width: 1365, height: 900 });
		await page.setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36');
		await page.setExtraHTTPHeaders({ 'Accept-Language': 'fr-FR,fr;q=0.9' });
		await page.goto(url.toString(), { waitUntil: 'domcontentloaded', timeout: 30000 });

		try {
			await page.waitForFunction(() => document.title !== 'Just a moment...', { timeout: 15000 });
		} catch {
			// The title check below returns a clear failure if the challenge remains.
		}

		if ((await page.title()) === 'Just a moment...') {
			throw new Error('Security verification did not complete');
		}

		process.stdout.write(await page.content());
	} finally {
		await browser.close();
	}
})().catch(error => {
	console.error(error.message);
	process.exit(1);
});
