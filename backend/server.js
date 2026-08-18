const express = require('express');
const fs = require('fs').promises;
const path = require('path');
const cors = require('cors');
const adminRoutes = require('./adminRoutes');
const app = express();

app.use(cors());
app.use(express.json({ limit: '100mb' }));
app.use('/api/admin', adminRoutes);

const PORT = process.env.PORT || 10000;
const DATA_DIR = './data';
let masterIndex = {};

async function init() {
  try {
    const idx = await fs.readFile(path.join(DATA_DIR, 'index.json'), 'utf8');
    masterIndex = JSON.parse(idx);
    console.log('Index loaded:', Object.keys(masterIndex.divisions || {}).length, 'divisions');
  } catch(e) {
    masterIndex = { divisions: {}, totalVoters: 0, lastUpdated: new Date().toISOString() };
  }
}
function getUpazilaPath(division, district, upazila) { return path.join(DATA_DIR, 'divisions', division, district, upazila); }
async function loadJSON(filePath) { try { return JSON.parse(await fs.readFile(filePath, 'utf8')); } catch { return null; } }
async function loadVoters(filePath) { const data = await loadJSON(filePath); return Array.isArray(data) ? data : []; }

app.get('/', (req, res) => res.json({ status:'ok', message:'Bangladesh Voter API', divisions:Object.keys(masterIndex.divisions || {}).length, totalVoters:masterIndex.totalVoters || 0 }));
app.get('/api/health', (req, res) => res.json({ status:'ok', uptime:process.uptime() }));
app.get('/api/divisions', async (req,res) => { const divisions=Object.keys(masterIndex.divisions||{}).map(key=>({id:key,...masterIndex.divisions[key]})); res.json({count:divisions.length,divisions}); });
app.get('/api/districts/:division', async (req,res) => { const index=await loadJSON(path.join(DATA_DIR,'divisions',req.params.division,'index.json')); if(!index)return res.status(404).json({error:'Division not found'}); const districts=Object.keys(index.districts||{}).map(key=>({id:key,...index.districts[key]})); res.json({count:districts.length,districts}); });
app.get('/api/upazilas/:division/:district', async (req,res) => { const index=await loadJSON(path.join(DATA_DIR,'divisions',req.params.division,req.params.district,'index.json')); if(!index)return res.status(404).json({error:'District not found'}); const upazilas=Object.keys(index.upazilas||{}).map(key=>({id:key,...index.upazilas[key]})); res.json({count:upazilas.length,upazilas}); });
app.get('/api/unions/:division/:district/:upazila', async (req,res) => { const dir=getUpazilaPath(req.params.division,req.params.district,req.params.upazila); try { const files=await fs.readdir(dir),unions=[]; for(const file of files){if(file==='index.json')continue;const stat=await fs.stat(path.join(dir,file));if(stat.isDirectory()){const idx=await loadJSON(path.join(dir,file,'index.json'));unions.push({id:file,...(idx||{})});}} res.json({count:unions.length,unions}); } catch { res.status(404).json({error:'Upazila not found'}); } });
app.get('/api/wards/:division/:district/:upazila/:union', async (req,res) => { const dir=path.join(getUpazilaPath(req.params.division,req.params.district,req.params.upazila),req.params.union); try { const wards=(await fs.readdir(dir)).filter(f=>f.endsWith('.json')&&f!=='index.json').map(f=>f.replace('.json','')); res.json({count:wards.length,wards}); } catch { res.status(404).json({error:'Union not found'}); } });
app.get('/api/voters/:division/:district/:upazila/:union/:ward', async (req,res) => { const {division,district,upazila,union,ward}=req.params; const voters=await loadVoters(path.join(getUpazilaPath(division,district,upazila),union,`${ward}.json`)); const {gender,ageFrom,ageTo,page=1,limit=50}=req.query; let filtered=voters;if(gender)filtered=filtered.filter(v=>v.gender===gender);if(ageFrom)filtered=filtered.filter(v=>v.age>=parseInt(ageFrom));if(ageTo)filtered=filtered.filter(v=>v.age<=parseInt(ageTo));const p=parseInt(page),l=parseInt(limit),start=(p-1)*l;res.json({count:Math.min(l,Math.max(0,filtered.length-start)),total:filtered.length,page:p,totalPages:Math.ceil(filtered.length/l),voters:filtered.slice(start,start+l)}); });
app.get('/api/search', async (req,res) => { const {q,division,district,upazila,union,ward,gender,ageFrom,ageTo,limit=50}=req.query;if(!q&&!division)return res.status(400).json({error:'Query (q) or division required'});const term=q?q.toLowerCase():null;let results=[],searchedFiles=0;const divisions=division?[division]:Object.keys(masterIndex.divisions||{});for(const div of divisions){if(results.length>=+limit)break;const di=await loadJSON(path.join(DATA_DIR,'divisions',div,'index.json'));const districts=district?[district]:Object.keys(di?.districts||{});for(const dist of districts){if(results.length>=+limit)break;const ui=await loadJSON(path.join(DATA_DIR,'divisions',div,dist,'index.json'));const upzs=upazila?[upazila]:Object.keys(ui?.upazilas||{});for(const upz of upzs){if(results.length>=+limit)break;const updir=getUpazilaPath(div,dist,upz);let unions=union?[union]:[];if(!union){try{unions=(await fs.readdir(updir)).filter(f=>f!=='index.json')}catch{continue;}}for(const un of unions){if(results.length>=+limit)break;const udir=path.join(updir,un);let wards=ward?[`${ward}.json`]:[];if(!ward){try{wards=(await fs.readdir(udir)).filter(f=>f.endsWith('.json')&&f!=='index.json')}catch{continue;}}for(const wf of wards){if(results.length>=+limit)break;searchedFiles++;const voters=await loadVoters(path.join(udir,wf));const matched=voters.filter(v=>{if(gender&&v.gender!==gender)return false;if(ageFrom&&v.age<+ageFrom)return false;if(ageTo&&v.age>+ageTo)return false;if(!term)return true;return [v.name,v.nameEn,v.voterId,v.voterNumber,v.fatherName,v.father,v.motherName,v.mother,v.husbandName,v.address?.village,v.address?.district,v.address?.upazila,v.address?.union].some(f=>f&&String(f).toLowerCase().includes(term));}).slice(0,+limit-results.length);results.push(...matched);}}}}}res.json({count:results.length,searchedFiles,voters:results}); });
app.get('/api/stats', async (req,res)=>res.json({total:masterIndex.totalVoters||0,divisions:Object.keys(masterIndex.divisions||{}).length,lastUpdated:masterIndex.lastUpdated}));

init().then(()=>app.listen(PORT,'0.0.0.0',()=>console.log(`Voter API running on port ${PORT}`)));
