import {readFileSync} from 'node:fs';
import assert from 'node:assert/strict';
const containers=JSON.parse(readFileSync('var/production-test/containers.json'));const networks=JSON.parse(readFileSync('var/production-test/networks.json'));const services=Object.fromEntries(containers.map(c=>[c.Config.Labels['com.docker.compose.service'],c]));
for(const service of ['app','preview-consumer','maintenance','render-worker','build-worker']){
 const c=services[service];assert(c,service+' absent');assert(c.Config.User&&c.Config.User!=='root'&&c.Config.User!=='0',service+' root');assert(c.HostConfig.ReadonlyRootfs);assert(c.HostConfig.CapDrop.includes('ALL'));assert(c.HostConfig.SecurityOpt.includes('no-new-privileges:true'));assert(c.HostConfig.Memory>0&&c.HostConfig.NanoCpus>0&&c.HostConfig.PidsLimit>0);assert(!c.Mounts.some(m=>m.Type==='bind'&&!m.Destination.startsWith('/run/secrets/')),'Source bind mount in '+service);assert(!c.Mounts.some(m=>m.Destination.includes('docker.sock')));assert(c.Config.Env.includes('APP_DEBUG=0')||service.endsWith('worker'));assert(c.State.Health?.Status==='healthy',service+' unhealthy');
}
for(const service of ['database','preview-consumer','maintenance','render-worker','build-worker'])assert.equal(Object.keys(services[service].HostConfig.PortBindings||{}).length,0,service+' publishes ports');
for(const service of ['render-worker','build-worker']){
 const c=services[service];assert.equal(Object.keys(c.NetworkSettings.Networks).length,1);assert(!c.Config.Env.some(v=>/^(APP_SECRET|DATABASE_URL|POSTGRES_PASSWORD|STYLEGUIDE_TOKEN)/.test(v)));assert.equal(c.Mounts.filter(m=>m.Destination.startsWith('/run/secrets/')).length,1);assert(c.Mounts.some(m=>m.Destination==='/run/secrets/'+(service==='render-worker'?'render_token':'build_token')));
}
assert(networks.filter(n=>n.Internal).length>=2);const appPort=Object.values(services.app.HostConfig.PortBindings).flat();assert(appPort.every(p=>p.HostIp==='127.0.0.1'));
const actualIds=Object.fromEntries(Object.entries(services).map(([name,c])=>[name,c.Image]));console.log(JSON.stringify({result:'PASS: actual production privileges, resource limits, private networks/ports, runtime secrets, immutable source and health verified',images:actualIds},null,2));
