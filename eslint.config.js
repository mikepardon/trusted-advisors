import js from "@eslint/js";
import { defineConfig, globalIgnores } from "eslint/config";
import tseslint from "typescript-eslint";
import eslintConfigPrettier from "eslint-config-prettier";
import eslintPluginVue from "eslint-plugin-vue";
import vueEslintParser from "vue-eslint-parser";
import globals from "globals";
import eslintPluginUnicorn from "eslint-plugin-unicorn";

export default defineConfig([
    globalIgnores([
        "vendor/**",
        "public/build/**",
        "public/hot",
        "bootstrap/ssr/**",
        "storage/**",
    ]),
    {
        extends: [
            js.configs.recommended,
            eslintPluginUnicorn.configs["flat/recommended"],
            tseslint.configs.strict,
            tseslint.configs.stylistic,
            eslintPluginVue.configs["flat/recommended"],
        ],
        languageOptions: {
            parser: vueEslintParser,
            parserOptions: {
                parser: tseslint.parser,
                sourceType: "module",
            },
            globals: {
                ...globals.builtin,
                ...globals.browser,
                axios: "readonly",
                route: "readonly",
            },
        },
        rules: {
            "@typescript-eslint/no-explicit-any": "error",
            "@typescript-eslint/consistent-type-imports": "error",

            "vue/block-lang": [
                "error",
                {
                    script: { lang: "ts" },
                    template: { lang: "html" },
                    style: { lang: ["css", "scss"] },
                },
            ],
            "vue/component-name-in-template-casing": ["error", "PascalCase"],
            "vue/component-api-style": ["error", ["script-setup"]],
            "vue/component-options-name-casing": ["error", "PascalCase"],
            "vue/custom-event-name-casing": ["error", "camelCase"],
            "vue/define-emits-declaration": ["error", "type-literal"],
            "vue/define-macros-order": ["error"],
            "vue/define-props-declaration": ["error", "type-based"],
            "vue/no-empty-component-block": ["error"],
            "vue/no-import-compiler-macros": ["error"],
            "vue/no-ref-object-reactivity-loss": ["error"],
            "vue/no-setup-props-reactivity-loss": ["error"],
            "vue/no-this-in-before-route-enter": ["error"],
            "vue/no-useless-mustaches": ["error"],
            "vue/no-useless-v-bind": ["error"],
            "vue/padding-line-between-blocks": ["error"],
        },
    },
    eslintConfigPrettier,
    {
        files: ["**/*.ts", "**/*.vue"],
        rules: {
            "no-undef": "off",
            "unicorn/prefer-global-this": "off",
        },
    },
    {
        files: ["**/*.vue"],
        rules: {
            "unicorn/filename-case": [
                "error",
                {
                    case: "pascalCase",
                    // Filenames only: Laravel mandates lowercase directories
                    // (resources/js/...), which can never be PascalCase.
                    checkDirectories: false,
                },
            ],
            "unicorn/no-useless-undefined": "off",
            "unicorn/prevent-abbreviations": "off",
        },
    },
    {
        files: ["**/composables/*.ts"],
        rules: {
            "unicorn/filename-case": [
                "error",
                {
                    case: "camelCase",
                    // Filenames only: the composables directory itself is
                    // lowercase, as is the rest of the Laravel tree above it.
                    checkDirectories: false,
                },
            ],
        },
    },
]);
