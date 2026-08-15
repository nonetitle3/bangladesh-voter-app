import { useState, useEffect, useCallback } from 'react';

// Change this to your Render backend URL after deployment
const API = process.env.REACT_APP_API_URL || 'https://bangladesh-voter-api.onrender.com/api';

export default function App() {
  const [divisions, setDivisions] = useState([]);
  const [districts, setDistricts] = useState([]);
  const [upazilas, setUpazilas] = useState([]);
  const [unions, setUnions] = useState([]);
  const [wards, setWards] = useState([]);

  const [selectedDivision, setSelectedDivision] = useState('');
  const [selectedDistrict, setSelectedDistrict] = useState('');
  const [selectedUpazila, setSelectedUpazila] = useState('');
  const [selectedUnion, setSelectedUnion] = useState('');
  const [selectedWard, setSelectedWard] = useState('');

  const [searchQuery, setSearchQuery] = useState('');
  const [gender, setGender] = useState('');
  const [ageFrom, setAgeFrom] = useState('');
  const [ageTo, setAgeTo] = useState('');

  const [voters, setVoters] = useState([]);
  const [stats, setStats] = useState({});
  const [loading, setLoading] = useState(false);
  const [viewMode, setViewMode] = useState('search');
  const [error, setError] = useState(null);

  useEffect(() => {
    fetch(`${API}/divisions`).then(r => r.json()).then(d => setDivisions(d.divisions || [])).catch(e => setError('API connection failed'));
    fetch(`${API}/stats`).then(r => r.json()).then(setStats).catch(() => {});
  }, []);

  useEffect(() => {
    if (!selectedDivision) { setDistricts([]); return; }
    fetch(`${API}/districts/${selectedDivision}`).then(r => r.json()).then(d => setDistricts(d.districts || []));
    setSelectedDistrict(''); setSelectedUpazila(''); setSelectedUnion(''); setSelectedWard('');
  }, [selectedDivision]);

  useEffect(() => {
    if (!selectedDistrict) { setUpazilas([]); return; }
    fetch(`${API}/upazilas/${selectedDivision}/${selectedDistrict}`).then(r => r.json()).then(d => setUpazilas(d.upazilas || []));
    setSelectedUpazila(''); setSelectedUnion(''); setSelectedWard('');
  }, [selectedDistrict]);

  useEffect(() => {
    if (!selectedUpazila) { setUnions([]); return; }
    fetch(`${API}/unions/${selectedDivision}/${selectedDistrict}/${selectedUpazila}`).then(r => r.json()).then(d => setUnions(d.unions || []));
    setSelectedUnion(''); setSelectedWard('');
  }, [selectedUpazila]);

  useEffect(() => {
    if (!selectedUnion) { setWards([]); return; }
    fetch(`${API}/wards/${selectedDivision}/${selectedDistrict}/${selectedUpazila}/${selectedUnion}`).then(r => r.json()).then(d => setWards(d.wards || []));
    setSelectedWard('');
  }, [selectedUnion]);

  const handleSearch = useCallback(async () => {
    setLoading(true); setError(null);
    const params = new URLSearchParams();
    if (searchQuery) params.set('q', searchQuery);
    if (selectedDivision) params.set('division', selectedDivision);
    if (selectedDistrict) params.set('district', selectedDistrict);
    if (selectedUpazila) params.set('upazila', selectedUpazila);
    if (selectedUnion) params.set('union', selectedUnion);
    if (selectedWard) params.set('ward', selectedWard);
    if (gender) params.set('gender', gender);
    if (ageFrom) params.set('ageFrom', ageFrom);
    if (ageTo) params.set('ageTo', ageTo);
    params.set('limit', '100');

    try {
      const res = await fetch(`${API}/search?${params}`);
      const data = await res.json();
      setVoters(data.voters || []);
    } catch (e) { setError('Search failed. Check API URL.'); }
    setLoading(false);
  }, [searchQuery, selectedDivision, selectedDistrict, selectedUpazila, selectedUnion, selectedWard, gender, ageFrom, ageTo]);

  const handleBrowse = useCallback(async () => {
    if (!selectedWard) return;
    setLoading(true); setError(null);
    const params = new URLSearchParams();
    if (gender) params.set('gender', gender);
    if (ageFrom) params.set('ageFrom', ageFrom);
    if (ageTo) params.set('ageTo', ageTo);

    try {
      const res = await fetch(`${API}/voters/${selectedDivision}/${selectedDistrict}/${selectedUpazila}/${selectedUnion}/${selectedWard}?${params}`);
      const data = await res.json();
      setVoters(data.voters || []);
    } catch (e) { setError('Browse failed. Check API URL.'); }
    setLoading(false);
  }, [selectedDivision, selectedDistrict, selectedUpazila, selectedUnion, selectedWard, gender, ageFrom, ageTo]);

  const clearFilters = () => {
    setSelectedDivision(''); setSelectedDistrict(''); setSelectedUpazila(''); setSelectedUnion(''); setSelectedWard('');
    setSearchQuery(''); setGender(''); setAgeFrom(''); setAgeTo(''); setVoters([]); setError(null);
  };

  const getDivisionName = (id) => divisions.find(d => d.id === id)?.name || id;

  return (
    <div className="min-h-screen bg-slate-50">
      <div className="bg-white border-b border-slate-200 sticky top-0 z-50">
        <div className="max-w-6xl mx-auto px-4 py-4">
          <div className="flex items-center justify-between flex-wrap gap-3">
            <div className="flex items-center gap-3">
              <span className="text-3xl">🇧🇩</span>
              <div>
                <h1 className="text-xl font-bold text-slate-800">ভোটার তথ্য অনুসন্ধান</h1>
                <p className="text-xs text-slate-500">বাংলাদেশ নির্বাচন কমিশন</p>
              </div>
            </div>
            <div className="flex gap-2">
              <button onClick={() => setViewMode('search')} className={`px-4 py-2 rounded-lg text-sm font-medium transition-colors ${viewMode === 'search' ? 'bg-emerald-500 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'}`}>🔍 সার্চ</button>
              <button onClick={() => setViewMode('browse')} className={`px-4 py-2 rounded-lg text-sm font-medium transition-colors ${viewMode === 'browse' ? 'bg-emerald-500 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'}`}>📂 ব্রাউজ</button>
            </div>
          </div>
        </div>
      </div>

      <div className="max-w-6xl mx-auto px-4 py-6">
        {error && <div className="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl mb-4">⚠️ {error}</div>}

        <div className="grid grid-cols-2 md:grid-cols-5 gap-3 mb-6">
          {[{label:'মোট ভোটার',value:stats.total?.toLocaleString('bn-BD'),color:'blue'},{label:'পুরুষ',value:stats.male?.toLocaleString('bn-BD'),color:'emerald'},{label:'মহিলা',value:stats.female?.toLocaleString('bn-BD'),color:'pink'},{label:'বিভাগ',value:stats.divisions,color:'purple'},{label:'জেলা',value:'৬৪',color:'orange'}].map(s => (
            <div key={s.label} className={`bg-white p-3 rounded-xl shadow-sm border border-${s.color}-100 text-center`}>
              <p className={`text-xl font-bold text-${s.color}-600`}>{s.value || '০'}</p>
              <p className="text-xs text-slate-500">{s.label}</p>
            </div>
          ))}
        </div>

        <div className="bg-white rounded-xl shadow-sm border border-slate-200 p-5 mb-6">
          <h3 className="font-bold text-slate-800 mb-4 flex items-center gap-2"><span>📍</span> ঠিকানা অনুসারে ফিল্টার</h3>

          <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3 mb-4">
            <div>
              <label className="block text-xs font-medium text-slate-500 mb-1">বিভাগ</label>
              <select value={selectedDivision} onChange={e => setSelectedDivision(e.target.value)} className="w-full px-3 py-2 rounded-lg border border-slate-200 bg-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                <option value="">সব বিভাগ</option>
                {divisions.map(d => <option key={d.id} value={d.id}>{d.name}</option>)}
              </select>
            </div>
            <div>
              <label className="block text-xs font-medium text-slate-500 mb-1">জেলা</label>
              <select value={selectedDistrict} onChange={e => setSelectedDistrict(e.target.value)} disabled={!selectedDivision} className="w-full px-3 py-2 rounded-lg border border-slate-200 bg-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 disabled:opacity-50">
                <option value="">সব জেলা</option>
                {districts.map(d => <option key={d.id} value={d.id}>{d.name || d.id}</option>)}
              </select>
            </div>
            <div>
              <label className="block text-xs font-medium text-slate-500 mb-1">উপজেলা</label>
              <select value={selectedUpazila} onChange={e => setSelectedUpazila(e.target.value)} disabled={!selectedDistrict} className="w-full px-3 py-2 rounded-lg border border-slate-200 bg-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 disabled:opacity-50">
                <option value="">সব উপজেলা</option>
                {upazilas.map(d => <option key={d.id} value={d.id}>{d.name || d.id}</option>)}
              </select>
            </div>
            <div>
              <label className="block text-xs font-medium text-slate-500 mb-1">ইউনিয়ন/ওয়ার্ড</label>
              <select value={selectedUnion} onChange={e => setSelectedUnion(e.target.value)} disabled={!selectedUpazila} className="w-full px-3 py-2 rounded-lg border border-slate-200 bg-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 disabled:opacity-50">
                <option value="">সব ইউনিয়ন</option>
                {unions.map(d => <option key={d.id} value={d.id}>{d.name || d.id}</option>)}
              </select>
            </div>
            <div>
              <label className="block text-xs font-medium text-slate-500 mb-1">ওয়ার্ড নং</label>
              <select value={selectedWard} onChange={e => setSelectedWard(e.target.value)} disabled={!selectedUnion} className="w-full px-3 py-2 rounded-lg border border-slate-200 bg-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 disabled:opacity-50">
                <option value="">সব ওয়ার্ড</option>
                {wards.map(w => <option key={w} value={w}>{w}</option>)}
              </select>
            </div>
            <div>
              <label className="block text-xs font-medium text-slate-500 mb-1">লিঙ্গ</label>
              <select value={gender} onChange={e => setGender(e.target.value)} className="w-full px-3 py-2 rounded-lg border border-slate-200 bg-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                <option value="">সব</option>
                <option value="male">পুরুষ</option>
                <option value="female">মহিলা</option>
              </select>
            </div>
          </div>

          <div className="flex flex-wrap gap-3 items-end">
            <div className="flex-1 min-w-[200px]">
              <label className="block text-xs font-medium text-slate-500 mb-1">{viewMode === 'search' ? 'নাম/ভোটার নম্বর/ঠিকানা' : 'অতিরিক্ত ফিল্টার'}</label>
              {viewMode === 'search' ? (
                <input value={searchQuery} onChange={e => setSearchQuery(e.target.value)} onKeyPress={e => e.key === 'Enter' && handleSearch()} placeholder="খুঁজুন..." className="w-full px-3 py-2 rounded-lg border border-slate-200 focus:outline-none focus:ring-2 focus:ring-emerald-500" />
              ) : (
                <div className="flex gap-2">
                  <input type="number" value={ageFrom} onChange={e => setAgeFrom(e.target.value)} placeholder="বয়স থেকে" className="w-24 px-3 py-2 rounded-lg border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500" />
                  <input type="number" value={ageTo} onChange={e => setAgeTo(e.target.value)} placeholder="বয়স পর্যন্ত" className="w-24 px-3 py-2 rounded-lg border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500" />
                </div>
              )}
            </div>
            <button onClick={viewMode === 'search' ? handleSearch : handleBrowse} disabled={loading || (viewMode === 'browse' && !selectedWard)} className="px-6 py-2 bg-emerald-500 text-white rounded-lg font-medium hover:bg-emerald-600 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
              {loading ? '⌛ লোড হচ্ছে...' : viewMode === 'search' ? '🔍 সার্চ' : '📂 দেখুন'}
            </button>
            <button onClick={clearFilters} className="px-4 py-2 bg-slate-100 text-slate-600 rounded-lg font-medium hover:bg-slate-200 transition-colors">🔄 ক্লিয়ার</button>
          </div>

          {(selectedDivision || selectedDistrict) && (
            <div className="mt-4 flex items-center gap-2 text-sm text-slate-500 flex-wrap">
              <span className="font-medium">📍 নির্বাচিত:</span>
              {selectedDivision && <span className="px-2 py-1 bg-emerald-100 text-emerald-700 rounded-md">{getDivisionName(selectedDivision)}</span>}
              {selectedDistrict && <><span>→</span><span className="px-2 py-1 bg-blue-100 text-blue-700 rounded-md">{selectedDistrict}</span></>}
              {selectedUpazila && <><span>→</span><span className="px-2 py-1 bg-orange-100 text-orange-700 rounded-md">{selectedUpazila}</span></>}
              {selectedUnion && <><span>→</span><span className="px-2 py-1 bg-purple-100 text-purple-700 rounded-md">{selectedUnion}</span></>}
              {selectedWard && <><span>→</span><span className="px-2 py-1 bg-pink-100 text-pink-700 rounded-md">{selectedWard}</span></>}
            </div>
          )}
        </div>

        <div className="space-y-3">
          {voters.length > 0 && (
            <div className="flex items-center justify-between mb-3">
              <p className="text-sm text-slate-500"><span className="font-medium text-slate-700">{voters.length.toLocaleString('bn-BD')}</span> জন ভোটার পাওয়া গেছে</p>
              <button onClick={() => { const csv = voters.map(v => `${v.voterId},${v.name},${v.fatherName},${v.motherName},${v.gender},${v.age},${v.address?.village},${v.pollingStation?.centerName}`).join('\n'); const blob = new Blob(['ভোটার নং,নাম,পিতার নাম,মাতার নাম,লিঙ্গ,বয়স,গ্রাম,কেন্দ্র\n' + csv], { type: 'text/csv' }); const url = URL.createObjectURL(blob); const a = document.createElement('a'); a.href = url; a.download = 'voters.csv'; a.click(); }} className="px-4 py-2 bg-blue-500 text-white rounded-lg text-sm font-medium hover:bg-blue-600 transition-colors">📥 CSV ডাউনলোড</button>
            </div>
          )}

          {voters.map((voter, i) => (
            <div key={i} className="bg-white rounded-xl shadow-sm border border-slate-100 hover:shadow-md transition-shadow overflow-hidden">
              <div className="p-4">
                <div className="flex justify-between items-start mb-3">
                  <div className="flex items-center gap-3">
                    <div className={`w-10 h-10 rounded-full flex items-center justify-center text-lg font-bold ${voter.gender === 'male' ? 'bg-blue-100 text-blue-600' : 'bg-pink-100 text-pink-600'}`}>{voter.gender === 'male' ? '👨' : '👩'}</div>
                    <div>
                      <h3 className="font-bold text-slate-800">{voter.name}</h3>
                      <p className="text-xs text-slate-400">{voter.nameEn}</p>
                    </div>
                  </div>
                  <span className={`px-3 py-1 rounded-full text-xs font-medium ${voter.gender === 'male' ? 'bg-blue-100 text-blue-700' : 'bg-pink-100 text-pink-700'}`}>{voter.gender === 'male' ? 'পুরুষ' : 'মহিলা'}</span>
                </div>
                <div className="grid md:grid-cols-2 gap-3 text-sm">
                  <div className="space-y-1">
                    <p className="text-slate-500"><span className="font-medium text-slate-700">🆔 ভোটার নং:</span> <span className="font-mono">{voter.voterId}</span></p>
                    <p className="text-slate-500"><span className="font-medium text-slate-700">👨 পিতা:</span> {voter.fatherName}</p>
                    <p className="text-slate-500"><span className="font-medium text-slate-700">👩 মাতা:</span> {voter.motherName}</p>
                    {voter.husbandName && <p className="text-slate-500"><span className="font-medium text-slate-700">👤 স্বামী:</span> {voter.husbandName}</p>}
                  </div>
                  <div className="space-y-1">
                    <p className="text-slate-500"><span className="font-medium text-slate-700">🎂 জন্ম তারিখ:</span> {voter.dob} ({voter.age} বছর)</p>
                    <p className="text-slate-500"><span className="font-medium text-slate-700">📍 ঠিকানা:</span> {voter.address?.village}, {voter.address?.union}, {voter.address?.upazila}</p>
                    <p className="text-slate-500"><span className="font-medium text-slate-700">🏛️ কেন্দ্র:</span> {voter.pollingStation?.centerName} (কক্ষ {voter.pollingStation?.roomNo}, বুথ {voter.pollingStation?.boothNo})</p>
                    <p className="text-slate-500"><span className="font-medium text-slate-700">🩸 রক্তের গ্রুপ:</span> {voter.bloodGroup || 'N/A'}</p>
                  </div>
                </div>
              </div>
              <div className="bg-slate-50 px-4 py-2 text-xs text-slate-400 flex justify-between">
                <span>📅 নিবন্ধন: {voter.registrationDate}</span>
                <span>📍 {voter.address?.district}, {voter.address?.division}</span>
              </div>
            </div>
          ))}

          {voters.length === 0 && !loading && !error && (
            <div className="text-center py-16">
              <p className="text-5xl mb-4">🗳️</p>
              <p className="text-slate-400 text-lg">ভোটার তথ্য অনুসন্ধান করুন</p>
              <p className="text-slate-400 text-sm mt-1">উপরের ফিল্টার ব্যবহার করে বিভাগ, জেলা, উপজেলা নির্বাচন করুন</p>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
