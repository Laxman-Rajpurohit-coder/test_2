const puppeteer = require('puppeteer');

(async () => {
  const browser = await puppeteer.launch({ headless: 'new' });
  const page = await browser.newPage();
  
  await page.setViewport({ width: 421, height: 788, isMobile: true, hasTouch: true });

  console.log("Navigating to login page...");
  await page.goto('http://localhost:8000/login', { waitUntil: 'networkidle0' });

  console.log("Logging in...");
  await page.type('input[name="email"]', 'admin@msg91.com');
  await page.type('input[name="password"]', 'password');
  
  // Hit enter to submit
  await Promise.all([
    page.waitForNavigation({ waitUntil: 'networkidle0' }),
    page.keyboard.press('Enter')
  ]);

  console.log("Taking screenshot of mobile dashboard...");
  const screenshotPath = 'C:\\Users\\msanj\\.gemini\\antigravity\\brain\\64d8ef90-cda2-4b87-9194-f911d97141f4\\mobile_hamburger_fix.png';
  await page.screenshot({ path: screenshotPath, fullPage: false });
  console.log(`Screenshot saved to: ${screenshotPath}`);

  await browser.close();
})();
