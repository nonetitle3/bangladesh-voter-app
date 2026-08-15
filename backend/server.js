const express = require('express');
const fs = require('fs').promises;
const path = require('path');
const cors = require('cors');
const app = express();

app.use(cors());
app.use(express.json({ limit: '100mb' }));

// Render uses PORT env var
const PORT = process.env.PORT || 10000;
const DATA_DIR = './data';
let masterIndex = {};

// ==================== INIT ====================
async function init() {
  try {
    const idx = await fs.readFile(path.join(DATA_DIR, 'index.json'), 'utf8');
    masterIndex = JSON.parse(idx);
    console.log('✅ Index loaded:', Object.keys(masterIndex.divisions || {}).length, 'divisions');
  } catch(e) {
    console.log('⚠️ No index found, creating...');
    masterIndex = { divisions: {}, totalVoters: 0, lastUpdated: new Date().toISOString() };
  }
}

// ==================== HELPERS ====================
function getUpazilaPath(division, district, upazila) {
  return path.join(DATA_DIR, 'divisions', division, district, upazila);
}

async function loadJSON(filePath) {
  try {
    const data = await fs.readFile(filePath, 'utf8');
    return JSON.parse(data);
  } catch { return null; }
}

async function loadVoters(filePath) {
  const data = await loadJSON(filePath);
  return Array.isArray(data) ? data : [];
}

// ==================== API ROUTES ====================

// Health check
app.get('/', (req, res) => {
  res.json({ 
    status: 'ok', 
    message: 'Bangladesh Voter API',
    divisions: Object.keys(masterIndex.divisions || {}).length,
    totalVoters: masterIndex.totalVoters || 0
  });
});

app.get('/api/health', (req, res) => {
  res.json({ status: 'ok', uptime: process.uptime() });
});

// Get all divisions
app.get('/api/divisions', async (req, res) => {
  const divisions = Object.keys(masterIndex.divisions || {}).map(key => ({
    id: key,
    ...masterIndex.divisions[key]
  }));
  res.json({ count: divisions.length, divisions });
});

// Get districts by division
app.get('/api/districts/:division', async (req, res) => {
  const { division } = req.params;
  const index = await loadJSON(path.join(DATA_DIR, 'divisions', division, 'index.json'));
  if (!index) return res.status(404).json({ error: 'Division not found' });

  const districts = Object.keys(index.districts || {}).map(key => ({
    id: key,
    ...index.districts[key]
  }));
  res.json({ count: districts.length, districts });
});

// Get upazilas by district
app.get('/api/upazilas/:division/:district', async (req, res) => {
  const { division, district } = req.params;
  const index = await loadJSON(path.join(DATA_DIR, 'divisions', division, district, 'index.json'));
  if (!index) return res.status(404).json({ error: 'District not found' });

  const upazilas = Object.keys(index.upazilas || {}).map(key => ({
    id: key,
    ...index.upazilas[key]
  }));
  res.json({ count: upazilas.length, upazilas });
});

// Get unions by upazila
app.get('/api/unions/:division/:district/:upazila', async (req, res) => {
  const { division, district, upazila } = req.params;
  const upazilaDir = getUpazilaPath(division, district, upazila);

  try {
    const files = await fs.readdir(upazilaDir);
    const unions = [];
    for (const file of files) {
      if (file === 'index.json') continue;
      const stat = await fs.stat(path.join(upazilaDir, file));
      if (stat.isDirectory()) {
        const idx = await loadJSON(path.join(upazilaDir, file, 'index.json'));
        unions.push({ id: file, ...idx });
      }
    }
    res.json({ count: unions.length, unions });
  } catch {
    res.status(404).json({ error: 'Upazila not found' });
  }
});

// Get wards by union
app.get('/api/wards/:division/:district/:upazila/:union', async (req, res) => {
  const { division, district, upazila, union } = req.params;
  const unionDir = path.join(getUpazilaPath(division, district, upazila), union);

  try {
    const files = await fs.readdir(unionDir);
    const wards = files
      .filter(f => f.endsWith('.json') && f !== 'index.json')
      .map(f => f.replace('.json', ''));
    res.json({ count: wards.length, wards });
  } catch {
    res.status(404).json({ error: 'Union not found' });
  }
});

// Get voters by exact address
app.get('/api/voters/:division/:district/:upazila/:union/:ward', async (req, res) => {
  const { division, district, upazila, union, ward } = req.params;
  const filePath = path.join(getUpazilaPath(division, district, upazila), union, `${ward}.json`);

  const voters = await loadVoters(filePath);
  const { gender, ageFrom, ageTo, page = 1, limit = 50 } = req.query;

  let filtered = voters;
  if (gender) filtered = filtered.filter(v => v.gender === gender);
  if (ageFrom) filtered = filtered.filter(v => v.age >= parseInt(ageFrom));
  if (ageTo) filtered = filtered.filter(v => v.age <= parseInt(ageTo));

  const start = (parseInt(page) - 1) * parseInt(limit);
  const paginated = filtered.slice(start, start + parseInt(limit));

  res.json({
    count: paginated.length,
    total: filtered.length,
    page: parseInt(page),
    totalPages: Math.ceil(filtered.length / parseInt(limit)),
    voters: paginated
  });
});

// Search voters (name/id/address)
app.get('/api/search', async (req, res) => {
  const { q, division, district, upazila, union, ward, gender, ageFrom, ageTo, limit = 50 } = req.query;

  if (!q && !division) {
    return res.status(400).json({ error: 'Query (q) or division required' });
  }

  const searchTerm = q ? q.toLowerCase() : null;
  let results = [];
  let searchedFiles = 0;

  let divisionsToSearch = division ? [division] : Object.keys(masterIndex.divisions || {});

  for (const div of divisionsToSearch) {
    if (results.length >= parseInt(limit)) break;

    const divPath = path.join(DATA_DIR, 'divisions', div);
    let districtsToSearch = district ? [district] : [];

    if (!district) {
      try {
        const distIdx = await loadJSON(path.join(divPath, 'index.json'));
        districtsToSearch = Object.keys(distIdx?.districts || {});
      } catch { continue; }
    }

    for (const dist of districtsToSearch) {
      if (results.length >= parseInt(limit)) break;

      const distPath = path.join(divPath, dist);
      let upazilasToSearch = upazila ? [upazila] : [];

      if (!upazila) {
        try {
          const upzIdx = await loadJSON(path.join(distPath, 'index.json'));
          upazilasToSearch = Object.keys(upzIdx?.upazilas || {});
        } catch { continue; }
      }

      for (const upz of upazilasToSearch) {
        if (results.length >= parseInt(limit)) break;

        const upzPath = getUpazilaPath(div, dist, upz);
        let unionsToSearch = union ? [union] : [];

        if (!union) {
          try {
            const files = await fs.readdir(upzPath);
            unionsToSearch = files.filter(f => f !== 'index.json');
          } catch { continue; }
        }

        for (const un of unionsToSearch) {
          if (results.length >= parseInt(limit)) break;

          const unionDir = path.join(upzPath, un);
          let wardsToSearch = ward ? [`${ward}.json`] : [];

          if (!ward) {
            try {
              const files = await fs.readdir(unionDir);
              wardsToSearch = files.filter(f => f.endsWith('.json') && f !== 'index.json');
            } catch { continue; }
          }

          for (const wFile of wardsToSearch) {
            if (results.length >= parseInt(limit)) break;
            searchedFiles++;

            const voters = await loadVoters(path.join(unionDir, wFile));

            const matched = voters.filter(v => {
              if (division && v.address?.division !== division) return false;
              if (district && v.address?.district !== district) return false;
              if (upazila && v.address?.upazila !== upazila) return false;
              if (union && v.address?.union !== union) return false;
              if (gender && v.gender !== gender) return false;
              if (ageFrom && v.age < parseInt(ageFrom)) return false;
              if (ageTo && v.age > parseInt(ageTo)) return false;

              if (searchTerm) {
                const fields = [
                  v.name, v.nameEn, v.voterId, v.fatherName,
                  v.motherName, v.husbandName, v.address?.village,
                  v.pollingStation?.centerName
                ];
                return fields.some(f => f && f.toLowerCase().includes(searchTerm));
              }
              return true;
            }).slice(0, parseInt(limit) - results.length);

            results.push(...matched);
          }
        }
      }
    }
  }

  res.json({ count: results.length, searchedFiles, voters: results });
});

// Stats
app.get('/api/stats', async (req, res) => {
  res.json({
    total: masterIndex.totalVoters || 0,
    divisions: Object.keys(masterIndex.divisions || {}).length,
    lastUpdated: masterIndex.lastUpdated
  });
});

// Start server
init().then(() => {
  app.listen(PORT, '0.0.0.0', () => {
    console.log(`🚀 Voter API running on port ${PORT}`);
  });
});
