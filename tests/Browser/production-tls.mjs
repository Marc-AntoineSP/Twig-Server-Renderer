import {chromium,expect} from '@playwright/test';
import {login} from './helpers.js';
const browser=await chromium.launch();
try{
    const context=await browser.newContext({baseURL:'https://proxy:8443',ignoreHTTPSErrors:true});const page=await context.newPage();await login(page,'admin');const cookies=await context.cookies();const session=cookies.find(c=>c.httpOnly);expect(session).toBeTruthy();expect(session.secure).toBe(true);expect(session.sameSite).toBe('Lax');
    const response=await page.request.get('/');expect(response.headers()['strict-transport-security']).toContain('max-age=');expect(response.headers()['x-debug-token']).toBeUndefined();
    const direct=await browser.newContext({baseURL:'http://studio:8080',extraHTTPHeaders:{'X-Forwarded-Proto':'https'}});await direct.newPage().then(p=>p.goto('/login'));expect((await direct.cookies()).find(c=>c.httpOnly)?.secure).toBe(false);
    console.log('PASS: actual local HTTPS proxy login uses Secure/HttpOnly/SameSite cookies; direct forged forwarded headers remain untrusted. Internal test CA only.');
}finally{await browser.close();}
