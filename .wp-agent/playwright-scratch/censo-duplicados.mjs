/**
 * Capturas de tela do relatório de duplicados (job 6a7b0022).
 * Base local = dump de produção de 11/08. Somente leitura na aplicação.
 */
import { chromium } from 'playwright';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const BASE = process.env.BASE_URL || 'https://revski-main.ddev.site:33177';
const EMAIL = 'darvin@envolvelabs.com.br';
const PASSWORD = 'PlaywrightLocal1!';
const OUT = path.resolve(__dirname, '../screenshots');

fs.mkdirSync(OUT, { recursive: true });

async function login(page) {
  await page.goto(`${BASE}/`, { waitUntil: 'networkidle', timeout: 60000 });
  await page.waitForSelector('#email', { timeout: 30000 });
  await page.fill('#email', EMAIL);
  await page.fill('#password', PASSWORD);
  await Promise.all([
    page.waitForURL((url) => !url.pathname.endsWith('/'), { timeout: 45000 }).catch(() => {}),
    page.click('button[type="submit"]'),
  ]);
  await page.waitForTimeout(1500);
}

async function busca(page, termo, arquivo) {
  await page.goto(`${BASE}/pacientes?search=${encodeURIComponent(termo)}`, {
    waitUntil: 'networkidle',
    timeout: 60000,
  });
  await page.waitForTimeout(1200);
  const file = path.join(OUT, arquivo);
  await page.screenshot({ path: file, fullPage: true });
  const linhas = await page.locator('table tbody tr').count();
  console.log(`${termo} -> ${arquivo} (${linhas} linhas)`);
  return { termo, arquivo, linhas };
}

const browser = await chromium.launch();
const context = await browser.newContext({
  ignoreHTTPSErrors: true,
  viewport: { width: 1400, height: 1000 },
});
const page = await context.newPage();

const resultado = [];
try {
  await login(page);
  resultado.push(await busca(page, 'giova', '6a7b0022-01-busca-giova.png'));
  resultado.push(await busca(page, 'kantowitz', '6a7b0022-02-busca-kantowitz.png'));
  resultado.push(await busca(page, 'jucineide', '6a7b0022-03-busca-jucineide.png'));
  resultado.push(await busca(page, 'roberta de andrade', '6a7b0022-04-busca-roberta.png'));
} finally {
  await browser.close();
}

fs.writeFileSync(
  path.resolve(__dirname, '../share/censo-duplicados-screenshots.json'),
  JSON.stringify({ base: BASE, capturas: resultado }, null, 2)
);
console.log('ok');
