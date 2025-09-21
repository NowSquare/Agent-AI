export default {
    root: true,
    env: { browser: true, es2022: true },
    parserOptions: { ecmaVersion: 'latest', sourceType: 'module' },
    extends: ['eslint:recommended', 'plugin:import/recommended', 'prettier'],
    rules: { 'import/order': ['warn', { 'newlines-between': 'always' }] },
  };
  