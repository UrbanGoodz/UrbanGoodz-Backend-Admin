#!/usr/bin/env node
'use strict';

const fs = require('fs');
const path = require('path');
const [backendArg, shopperArg, vendorArg, driverArg] = process.argv.slice(2);
const backend = path.resolve(backendArg || process.cwd());
const apps = [
  ['SHOPPER APP', 'customer', shopperArg],
  ['VENDOR APP', 'vendor', vendorArg],
  ['DRIVER APP', 'driver', driverArg],
].filter((x) => x[2] && fs.existsSync(x[2])).map((x) => [x[0], x[1], path.resolve(x[2])]);
const out = path.join(backend, 'docs', 'audit');
fs.mkdirSync(out, { recursive: true });
const skip = new Set(['.git', '.dart_tool', 'build', 'node_modules', 'storage']);

function walk(root, test) {
  const files = [], stack = fs.existsSync(root) ? [root] : [];
  while (stack.length) {
    const dir = stack.pop();
    let entries = [];
    try { entries = fs.readdirSync(dir, { withFileTypes: true }); } catch (_) {}
    for (const entry of entries) {
      const full = path.join(dir, entry.name);
      if (entry.isDirectory()) { if (!skip.has(entry.name)) stack.push(full); }
      else if (test(full)) files.push(full);
    }
  }
  return files.sort();
}
const read = (f) => { try { return fs.readFileSync(f, 'utf8'); } catch (_) { return ''; } };
const rel = (root, f) => path.relative(root, f).split(path.sep).join('/');
const line = (s, i) => s.slice(0, i).split('\n').length;
const clean = (s, n = 180) => String(s || '').replace(/<[^>]+>/g, ' ')
  .replace(/\{\{[\s\S]*?\}\}/g, '{{…}}').replace(/@\w+(?:\([^)]*\))?/g, ' ')
  .replace(/[\r\n\t]+/g, ' ').replace(/\s+/g, ' ').trim().slice(0, n);
function attr(tag, name) {
  const m = tag.match(new RegExp(`\\b${name}\\s*=\\s*(?:"([^"]*)"|'([^']*)'|([^\\s>]+))`, 'i'));
  return m ? (m[1] || m[2] || m[3] || '') : '';
}
const quote = (v) => /[",\r\n]/.test(String(v ?? '')) ? `"${String(v ?? '').replace(/"/g, '""')}"` : String(v ?? '');
function csv(file, headers, rows) {
  fs.writeFileSync(file, [headers.join(','), ...rows.map((r) => headers.map((h) => quote(r[h])).join(','))].join('\n') + '\n');
}
function surface(view) {
  if (view.startsWith('admin-views/') || view.startsWith('layouts/admin/')) return ['ADMIN PORTAL', 'admin'];
  if (view.startsWith('business/')) return ['BUSINESS PORTAL', 'business'];
  if (view.startsWith('vendor-views/') || view.startsWith('layouts/vendor/')) return ['VENDOR WEB PORTAL', 'vendor'];
  if (view.startsWith('auth/')) return ['ADMIN PORTAL', 'guest/admin'];
  return ['PUBLIC WEBSITE', 'public/customer'];
}

const H = [
  'SURFACE','ROLE','SCREEN NAME','ROUTE OR MOBILE NAVIGATION PATH','SOURCE FILE','SOURCE LINE',
  'CONTROLLER OR VIEWMODEL','PERMISSION','VISIBLE TITLE','CONTROL TYPE','CONTROL NAME','TABS','CARDS',
  'TABLES','TABLE COLUMNS','BUTTONS','DROPDOWNS','FILTERS','SEARCH FIELDS','TEXT FIELDS','DATE PICKERS',
  'CHECKBOXES','RADIO BUTTONS','TOGGLES','UPLOAD CONTROLS','CAMERA CONTROLS','SCANNER CONTROLS',
  'MAP CONTROLS','PAGINATION','MODALS','MENUS','ACTION MENUS','STATUS BADGES','EMPTY STATES',
  'ERROR STATES','VALIDATION','EXPECTED ACTION','CURRENT ACTION','API ENDPOINT','DATABASE EFFECT',
  'NEXT ROLE VISIBILITY','NOTIFICATION','TEST COVERAGE','CURRENT STATUS','DEFECT','SEVERITY','FIX STATUS'
];
const rows = [];
const tests = walk(path.join(backend, 'tests'), (f) => /\.(php|js|ts)$/.test(f)).map(read).join('\n');
const routes = walk(path.join(backend, 'routes'), (f) => f.endsWith('.php')).map(read).join('\n');
function blank() { return Object.fromEntries(H.map((h) => [h, ''])); }
function flags(r, type, name, text) {
  r.TABS = /tab/i.test(text) || type === 'tab' ? name : '';
  r.CARDS = /card/i.test(text) ? name : '';
  r.TABLES = type === 'table' || type === 'list/table' ? name : '';
  r['TABLE COLUMNS'] = type === 'th' ? name : '';
  r.BUTTONS = type === 'button' ? name : '';
  r.DROPDOWNS = type === 'select' || type === 'dropdown' ? name : '';
  r.FILTERS = /filter/i.test(text) ? name : '';
  r['SEARCH FIELDS'] = /search/i.test(type + text) ? name : '';
  r['TEXT FIELDS'] = /text field|textarea|input:(text|email|tel|password|number)/.test(type) ? name : '';
  r['DATE PICKERS'] = /input:(date|datetime-local|time)/.test(type) ? name : '';
  r.CHECKBOXES = /checkbox/.test(type) ? name : '';
  r['RADIO BUTTONS'] = /radio/.test(type) ? name : '';
  r.TOGGLES = /toggle|switch/.test(type + text) ? name : '';
  r['UPLOAD CONTROLS'] = /upload|input:file/.test(type) ? name : '';
  r['CAMERA CONTROLS'] = /camera|photo/i.test(type + text) ? name : '';
  r['SCANNER CONTROLS'] = /scanner|barcode|qr/i.test(type + text) ? name : '';
  r['MAP CONTROLS'] = /map|latitude|longitude|location/i.test(type + text) ? name : '';
  r.PAGINATION = /paginat/i.test(text) ? name : '';
  r.MODALS = /modal/i.test(text) ? name : '';
  r.MENUS = /menu|dropdown/i.test(text) ? name : '';
  r['ACTION MENUS'] = /action/i.test(text) && /menu|dropdown/i.test(text) ? name : '';
  r['STATUS BADGES'] = /badge|status/i.test(text) ? name : '';
  r['EMPTY STATES'] = /empty|no.data|not.found/i.test(text) ? name : '';
  r['ERROR STATES'] = /error|invalid|alert-danger/i.test(text) ? name : '';
}

const viewRoot = path.join(backend, 'resources', 'views');
for (const file of walk(viewRoot, (f) => f.endsWith('.blade.php'))) {
  const s = read(file), view = rel(viewRoot, file), [surf, role] = surface(view);
  const title = clean(s.match(/<(?:h1|h2|h3|h4|title)\b[^>]*>([\s\S]*?)<\//i)?.[1] || path.basename(view));
  const perm = s.match(/module_permission_check\(\s*['"]([^'"]+)|@can\(\s*['"]([^'"]+)/);
  const re = /<(form|button|select|textarea|input|table|th|a)\b[\s\S]*?(?:\/>|<\/\1>|>)/gi;
  let m;
  while ((m = re.exec(s))) {
    const tag = m[0], el = m[1].toLowerCase(), input = el === 'input' ? (attr(tag, 'type') || 'text').toLowerCase() : '';
    let type = el === 'input' ? `input:${input}` : el;
    if (el === 'a') type = /dropdown|menu/i.test(tag) ? 'menu/action link' : 'link';
    if (el === 'button' && /toggle|switch/i.test(tag)) type = 'toggle';
    if (el === 'input' && input === 'file') type = 'upload';
    const rm = tag.match(/route\(\s*['"]([^'"]+)/), um = tag.match(/(?:href|action)\s*=\s*["'][^"']*(\/(?:api|admin|business|vendor)[^"'{} ]*)/i);
    const endpoint = rm ? `route:${rm[1]}` : (um?.[1] || '');
    const name = clean(attr(tag, 'aria-label') || attr(tag, 'title') || attr(tag, 'placeholder')
      || attr(tag, 'name') || attr(tag, 'id') || tag.replace(/^<[^>]+>/, '').replace(/<\/[^>]+>$/, '')) || `${type}@${line(s,m.index)}`;
    const token = rm?.[1] || attr(tag, 'id') || attr(tag, 'name');
    const routeSeen = rm && routes.includes(rm[1]);
    const r = blank();
    Object.assign(r, {
      SURFACE:surf, ROLE:role, 'SCREEN NAME':view, 'ROUTE OR MOBILE NAVIGATION PATH':endpoint || 'not statically resolved',
      'SOURCE FILE':`resources/views/${view}`, 'SOURCE LINE':line(s,m.index),
      'CONTROLLER OR VIEWMODEL':'requires route-level trace', PERMISSION:perm ? (perm[1]||perm[2]) : 'not proven in view',
      'VISIBLE TITLE':title, 'CONTROL TYPE':type, 'CONTROL NAME':name,
      VALIDATION:/\brequired\b/i.test(tag)?'required':'not declared on control',
      'EXPECTED ACTION':/button|link|form/.test(type)?'perform named navigation/mutation and expose final state':'capture/filter/validate input',
      'CURRENT ACTION':endpoint?`source references ${endpoint}`:'handler/action not proven from tag',
      'API ENDPOINT':um?.[1]||'', 'DATABASE EFFECT':'not proven by view source','NEXT ROLE VISIBILITY':'not proven',
      NOTIFICATION:'not proven','TEST COVERAGE':token&&token.length>3&&tests.includes(token)?'source token found; behavioral strength unproven':'none located by token',
      'CURRENT STATUS':endpoint&&routeSeen?'PARTIALLY WIRED':'UNKNOWN',
      DEFECT:endpoint&&!routeSeen?'route reference not resolved by static token search':'',
      SEVERITY:endpoint&&!routeSeen?'P1/P2 requires route trace':'','FIX STATUS':'AUDIT ONLY'
    });
    flags(r,type,name,tag); rows.push(r);
  }
}

const dartPatterns = [
  ['button',/\b(?:ElevatedButton|TextButton|OutlinedButton|IconButton|FloatingActionButton)\s*\(/],
  ['dropdown',/\b(?:DropdownButton|DropdownButtonFormField|PopupMenuButton)\s*</],
  ['text field',/\b(?:TextField|TextFormField)\s*\(/],['checkbox',/\bCheckbox(?:ListTile)?\s*\(/],
  ['radio',/\bRadio(?:ListTile)?\s*</],['toggle',/\bSwitch(?:ListTile)?\s*\(/],['tab',/\bTab\s*\(/],
  ['upload',/\b(?:ImagePicker|FilePicker|pickImage|pickFiles)\b/],['camera',/\b(?:CameraController|CameraPreview|camera)\b/i],
  ['scanner',/\b(?:barcode|qr|scanner|MobileScanner)\b/i],['map',/\b(?:GoogleMap|MapWidget|latitude|longitude)\b/],
  ['list/table',/\b(?:ListView|GridView|DataTable)\s*[.(]/],
];
for (const [surf, role, root] of apps) {
  const lib = path.join(root,'lib');
  for (const file of walk(lib,(f)=>f.endsWith('.dart'))) {
    const view=rel(lib,file); if(!/(screen|page|view|dialog|sheet|widget)\.dart$/i.test(view)) continue;
    const s=read(file), lines=s.split(/\r?\n/), title=clean(s.match(/class\s+(\w+)/)?.[1]||path.basename(view));
    lines.forEach((ln,i)=>{
      dartPatterns.forEach(([type,re])=>{
        if(!re.test(ln)) return;
        const ctx=lines.slice(i,i+8).join(' '), label=clean(ctx.match(/(?:Text|tooltip|labelText|hintText|semanticLabel)\s*(?::|\()\s*(?:const\s*)?['"]([^'"]+)/)?.[1]||`${type}@${i+1}`);
        const handler=ctx.match(/\b(onPressed|onTap|onChanged|onSelected|onSubmitted)\s*:\s*([^,\n}]+)/);
        const api=ctx.match(/['"](\/api\/[^'"]+|\/(?:business|admin|vendor)\/[^'"]+)['"]/);
        const nav=ctx.match(/(?:Get\.toNamed|Navigator\.\w+Named|pushNamed)\s*\([^'"]*['"]([^'"]+)/);
        const r=blank(); Object.assign(r,{
          SURFACE:surf,ROLE:role,'SCREEN NAME':view,'ROUTE OR MOBILE NAVIGATION PATH':nav?.[1]||'not statically resolved',
          'SOURCE FILE':`${surf.toLowerCase().replaceAll(' ','-')}:lib/${view}`,'SOURCE LINE':i+1,
          'CONTROLLER OR VIEWMODEL':clean(s.match(/(?:Get\.find|GetBuilder|Consumer)<(\w+)>/)?.[1]||'not statically resolved'),
          PERMISSION:'app/API authorization not proven by widget','VISIBLE TITLE':title,'CONTROL TYPE':type,'CONTROL NAME':label,
          VALIDATION:/validator\s*:/.test(ctx)?'validator present':'not proven',
          'EXPECTED ACTION':'perform named mobile interaction and expose final state',
          'CURRENT ACTION':handler?`${handler[1]} handler present`:'no handler proven in local source window',
          'API ENDPOINT':api?.[1]||'','DATABASE EFFECT':'not proven from mobile source','NEXT ROLE VISIBILITY':'not proven',
          NOTIFICATION:'not proven','TEST COVERAGE':'no trustworthy mobile behavioral evidence linked',
          'CURRENT STATUS':handler?'PARTIALLY WIRED':'UI ONLY',
          DEFECT:handler?'':'visible control/widget signal without locally proven handler',SEVERITY:handler?'':'P2','FIX STATUS':'AUDIT ONLY'
        }); flags(r,type,label,ctx); rows.push(r);
      });
    });
  }
}
csv(path.join(out,'URBAN_GOODZ_UI_CONTROL_CENSUS.csv'),H,rows);

const tables=new Map();
const ensure=(name)=>{if(!tables.has(name)) tables.set(name,{TABLE:name,'PROVEN CREATE MIGRATION':[],'ALTER MIGRATIONS':[],MODELS:[],'CONTROLLERS/SERVICES/OTHER USAGE':[],'FOREIGN KEY REFERENCES':[],STATUS:'',EVIDENCE:''});return tables.get(name)};
for(const file of walk(path.join(backend,'database','migrations'),f=>f.endsWith('.php'))){
  const s=read(file), f=rel(backend,file);
  for(const m of s.matchAll(/Schema::create\(\s*['"]([^'"]+)/g)) ensure(m[1])['PROVEN CREATE MIGRATION'].push(f);
  for(const m of s.matchAll(/Schema::table\(\s*['"]([^'"]+)/g)) ensure(m[1])['ALTER MIGRATIONS'].push(f);
  for(const m of s.matchAll(/->(?:on|constrained)\(\s*['"]([^'"]+)/g)) ensure(m[1])['FOREIGN KEY REFERENCES'].push(f);
}
for(const file of walk(path.join(backend,'app','Models'),f=>f.endsWith('.php'))){
  const s=read(file), m=s.match(/protected\s+\$table\s*=\s*['"]([^'"]+)/); if(m) ensure(m[1]).MODELS.push(rel(backend,file));
}
for(const file of walk(path.join(backend,'app'),f=>f.endsWith('.php'))){
  const s=read(file), used=new Set(), f=rel(backend,file);
  for(const m of s.matchAll(/(?:DB::table|->from|->join|->leftJoin|->rightJoin)\(\s*['"]([^'"]+)/g)) used.add(m[1]);
  for(const t of used) ensure(t)['CONTROLLERS/SERVICES/OTHER USAGE'].push(f);
}
const DH=['TABLE','PROVEN CREATE MIGRATION','ALTER MIGRATIONS','MODELS','CONTROLLERS/SERVICES/OTHER USAGE','FOREIGN KEY REFERENCES','STATUS','EVIDENCE'];
const db=[...tables.values()].sort((a,b)=>a.TABLE.localeCompare(b.TABLE)).map(r=>{
  const created=r['PROVEN CREATE MIGRATION'].length, altered=r['ALTER MIGRATIONS'].length;
  r.STATUS=created?'MIGRATION BASELINE PRESENT':altered?'BLOCKED: ALTERED WITHOUT PROVEN CREATE MIGRATION':'REFERENCED WITHOUT PROVEN CREATE MIGRATION';
  r.EVIDENCE=created?'Schema::create located in committed migration':'No Schema::create located; authoritative schema export required';
  DH.slice(1,6).forEach(h=>r[h]=[...new Set(r[h])].join('; ')); return r;
});
csv(path.join(out,'URBAN_GOODZ_DATABASE_TABLE_USAGE_MATRIX.csv'),DH,db);
const summary={generatedAt:new Date().toISOString(),bladeFiles:walk(viewRoot,f=>f.endsWith('.blade.php')).length,routeFiles:walk(path.join(backend,'routes'),f=>f.endsWith('.php')).length,migrations:walk(path.join(backend,'database','migrations'),f=>f.endsWith('.php')).length,uiControlRows:rows.length,databaseTableRows:db.length,uiBySurface:Object.fromEntries([...new Set(rows.map(r=>r.SURFACE))].sort().map(s=>[s,rows.filter(r=>r.SURFACE===s).length])),uiByControlType:Object.fromEntries([...new Set(rows.map(r=>r['CONTROL TYPE']))].sort().map(t=>[t,rows.filter(r=>r['CONTROL TYPE']===t).length]))};
fs.writeFileSync(path.join(out,'SOURCE_CENSUS_SUMMARY.json'),JSON.stringify(summary,null,2)+'\n');
console.log(JSON.stringify(summary,null,2));
