// Safe loader for the new voter-search API.
// This file is intentionally separate from server.js so the existing API can
// remain stable while the new search service is introduced.
const voterRoutes = require('./voterRoutes');

module.exports = function registerVoterRoutes(app) {
  app.use('/api/voter-search', voterRoutes);
  return app;
};
