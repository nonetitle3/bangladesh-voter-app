# 🇧🇩 Bangladesh Voter Information App

> GitHub + Render — সম্পূর্ণ ফ্রি ডিপ্লয়মেন্ট

## 🔗 লাইভ লিংক

| অংশ | লিংক |
|------|------|
| Frontend | `https://YOUR_USERNAME.github.io/bangladesh-voter-app` |
| Backend API | `https://bangladesh-voter-api.onrender.com` |

## 📁 প্রজেক্ট স্ট্রাকচার

```
bangladesh-voter-app/
├── backend/                    # Node.js API (Render)
│   ├── server.js               # Main API server
│   ├── package.json            # Dependencies
│   ├── render.yaml             # Render config
│   └── data/                   # Voter data (JSON files)
│       ├── index.json          # Master index
│       └── divisions/          # Division-wise data
├── frontend/                   # React App (GitHub Pages)
│   ├── public/
│   │   └── index.html
│   ├── src/
│   │   ├── index.js
│   │   └── App.js
│   └── package.json
└── README.md
```

## 🚀 A to Z সেটআপ

### ধাপ A: GitHub Repo তৈরি

1. github.com এ সাইন আপ → "New Repository"
2. Name: `bangladesh-voter-app` → Public → Create
3. ZIP ফাইল unzip → সব ফাইল এই ফোল্ডারে রাখুন

```bash
cd bangladesh-voter-app
git init
git add .
git commit -m "Initial setup"
git remote add origin https://github.com/YOUR_USERNAME/bangladesh-voter-app.git
git push -u origin main
```

### ধাপ B: Render-এ Backend Deploy

1. **render.com** এ সাইন আপ (GitHub দিয়ে)
2. Dashboard → "New" → "Web Service"
3. GitHub repo কানেক্ট: `bangladesh-voter-app`
4. Settings:
   - **Name**: `bangladesh-voter-api`
   - **Root Directory**: `backend`
   - **Build Command**: `npm install`
   - **Start Command**: `node server.js`
5. Environment Variables:
   - `NODE_ENV` = `production`
6. "Create Web Service" → Deploy!

> ⚠️ **Sleep Warning**: Render free tier 15 min idle থাকলে sleep হয়। প্রথম request-এ 30 sec লাগতে পারে।

### ধাপ C: GitHub Pages-এ Frontend

1. GitHub Repo → Settings → Pages
2. Source: **GitHub Actions**
3. `.github/workflows/pages.yml` ফাইল তৈরি করুন:

```yaml
name: Deploy to GitHub Pages
on:
  push:
    branches: [main]
jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: actions/setup-node@v4
        with: { node-version: '20' }
      - run: |
          cd frontend
          npm install
          REACT_APP_API_URL=https://bangladesh-voter-api.onrender.com/api npm run build
      - uses: peaceiris/actions-gh-pages@v3
        with:
          github_token: ${{ secrets.GITHUB_TOKEN }}
          publish_dir: ./frontend/build
```

4. Push করুন → Actions tab-এ deploy দেখুন

### ধাপ D: API URL Update

`frontend/src/App.js`-এ API URL আপডেট করুন:

```javascript
const API = 'https://bangladesh-voter-api.onrender.com/api';
```

## 📊 API Endpoints

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api/divisions` | GET | সব বিভাগ |
| `/api/districts/:division` | GET | বিভাগের জেলা |
| `/api/upazilas/:division/:district` | GET | জেলার উপজেলা |
| `/api/unions/:division/:district/:upazila` | GET | উপজেলার ইউনিয়ন |
| `/api/wards/.../:union` | GET | ইউনিয়নের ওয়ার্ড |
| `/api/voters/.../:ward` | GET | নির্দিষ্ট ওয়ার্ডের ভোটার |
| `/api/search?q=...` | GET | নাম/ID/ঠিকানা দিয়ে সার্চ |
| `/api/stats` | GET | পরিসংখ্যান |

## 📥 ডাটা ইমপোর্ট

### JSON Format
```json
{
  "serialNo": 1,
  "voterId": "১২৩৪৫৬৭৮৯০১",
  "name": "মোঃ আব্দুল করিম",
  "nameEn": "Md. Abdul Karim",
  "fatherName": "মোঃ আব্দুল রহিম",
  "motherName": "ফাতেমা বেগম",
  "dob": "1985-03-15",
  "age": 39,
  "gender": "male",
  "address": {
    "division": "dhaka",
    "district": "dhaka-district",
    "upazila": "dhanmondi",
    "union": "dhanmondi-west",
    "ward": "ward-1",
    "village": "ধানমন্ডি"
  },
  "pollingStation": {
    "centerNo": "101",
    "centerName": "ধানমন্ডি সরকারি উচ্চ বিদ্যালয়",
    "roomNo": "5",
    "boothNo": "3"
  }
}
```

### Folder Structure
```
data/
├── index.json
└── divisions/
    └── dhaka/
        ├── index.json
        └── dhaka-district/
            ├── index.json
            └── dhanmondi/
                ├── index.json
                └── dhanmondi-west/
                    ├── index.json
                    └── ward-1.json
```

## 💰 খরচ

| প্ল্যাটফর্ম | খরচ | সীমা |
|------------|------|------|
| GitHub | ফ্রি | Unlimited repos |
| GitHub Pages | ফ্রি | Static hosting |
| Render | ফ্রি | 512MB RAM, sleeps after 15min idle |
| **মোট** | **৳০** | **সম্পূর্ণ ফ্রি** |

## ⚠️ সীমাবদ্ধতা

- Render free: 15 min idle থাকলে sleep, wake up-এ 30 sec লাগে
- GitHub Pages: শুধু static site
- Render disk: 1GB (ডাটা বড় হলে external DB লাগবে)

## 🆘 সাহায্য

সমস্যা হলে GitHub Issues তৈরি করুন।

---
**Made with ❤️ for Bangladesh**
