// eslint.config.mjs
// Extension .mjs : le paquet est en CommonJS, un eslint.config.js y serait lu comme tel.
import js from '@eslint/js';
import tseslint from 'typescript-eslint';
import eslintConfigPrettier from 'eslint-config-prettier';

export default tseslint.config(
  js.configs.recommended,
  ...tseslint.configs.recommended,
  eslintConfigPrettier,
  {
    files: ['**/*.ts'],
    rules: {
      'no-undef': 'off',
    },
  },
);
