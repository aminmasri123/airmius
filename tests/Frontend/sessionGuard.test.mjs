import test from 'node:test';
import assert from 'node:assert/strict';
import { installSessionGuard } from '../../resources/js/sessionGuard.js';
function setup(user = {id:1}) {
 const events={}, listeners={}, redirects=[];
 const browser={document:{hidden:false,addEventListener:(name,fn)=>listeners[name]=fn},
   location:{replace:url=>redirects.push(url)},fetch:async()=>({status:204}),
   setInterval:fn=>listeners.tick=fn,addEventListener:(name,fn)=>listeners[name]=fn};
 installSessionGuard({on:(name,fn)=>events[name]=fn},{props:{auth:{user}}},browser);
 return {browser,events,listeners,redirects};
}
test('revoked session redirects exactly once and also checks on focus',async()=>{
 const s=setup();s.browser.fetch=async()=>({status:401});
 await s.listeners.focus();await s.listeners.tick();assert.deepEqual(s.redirects,['/login']);
});
test('guests, hidden tabs and network failures do not redirect',async()=>{
 const s=setup(null);s.browser.fetch=async()=>{throw Error('offline');};
 await s.listeners.tick();assert.deepEqual(s.redirects,[]);
 s.events.success({detail:{page:{props:{auth:{user:{id:1}}}}}});
 await s.listeners.tick();assert.deepEqual(s.redirects,[]);
 s.browser.document.hidden=true;s.browser.fetch=async()=>({status:401});
 await s.listeners.tick();assert.deepEqual(s.redirects,[]);
});
test('Inertia session expiry clears the page through full login navigation',()=>{
 const s=setup();let prevented=false;
 s.events.invalid({detail:{response:{status:419}},preventDefault:()=>prevented=true});
 assert.equal(prevented,true);assert.deepEqual(s.redirects,['/login']);
});
