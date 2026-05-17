module.exports = {
    root: true,
    extends: ['@nextcloud'],
    parserOptions: {
        tsconfigRootDir: __dirname,
        project: './tsconfig.json',
    },
    ignorePatterns: ['js/', 'node_modules/', 'build/', 'vendor/'],
    globals: {
        t: 'readonly',
        n: 'readonly',
        OC: 'readonly',
    },
    rules: {
        'no-console': ['warn', { allow: ['warn', 'error', 'info'] }],
    },
}
