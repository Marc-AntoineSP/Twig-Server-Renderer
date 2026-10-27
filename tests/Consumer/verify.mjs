import {chromium,expect} from '@playwright/test';
import {readFileSync} from 'node:fs';
const state=JSON.parse(readFileSync('var/consumer-state.json','utf8'));const browser=await chromium.launch();const page=await browser.newPage({viewport:{width:1280,height:900}});const requests=[],errors=[];
page.on('request',r=>requests.push(r.url()));page.on('pageerror',e=>errors.push(e.message));
try{
    await page.goto('http://consumer:8080/');await expect(page.getByRole('heading',{name:'Independent Symfony consumer',exact:true})).toBeVisible();
    await expect(page.locator('html')).toHaveAttribute('data-theme',state.cssName);await expect(page.locator('body')).toHaveCSS('font-family',/Georgia/);
    const layout=page.locator(`[data-package="${state.layout}"]`);await expect(layout.getByRole('status')).toHaveCount(2);await expect(layout.getByRole('status').first()).toContainText('First consumer instance');await expect(layout.locator('section').first()).toHaveCSS('grid-template-columns',/\d.*px \d.*px \d.*px/);
    await layout.getByRole('button',{name:'Dismiss notification',exact:true}).first().click();await expect(layout.getByRole('status')).toHaveCount(1);
    await page.setViewportSize({width:375,height:900});await expect(layout.locator('section').first()).toHaveCSS('grid-template-columns',/^\d+(?:\.\d+)?px$/);
    expect(requests.every(url=>url.startsWith('http://consumer:8080/'))).toBe(true);expect(errors).toEqual([]);
    console.log('PASS: production AssetMapper CSS, responsive utilities, typed theme, nested Twig and Stimulus run using only consumer-origin requests.');
}finally{await browser.close();}
