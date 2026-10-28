import {request} from '@playwright/test';
import {verifyTarget} from './ownership.js';

export default async function setup(config) {
    const client = await request.newContext({baseURL: config.projects[0].use.baseURL});
    try { await verifyTarget(client); } finally { await client.dispose(); }
}
