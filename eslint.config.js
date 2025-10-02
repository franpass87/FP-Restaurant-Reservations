import js from "@eslint/js";
import globals from "globals";

export default [
  {
    ignores: ["node_modules/**", "vendor/**", "build/**", "dist/**"]
  },
  {
    files: ["assets/js/**/*.{js,ts}", "scripts/**/*.{js,ts}"],
    languageOptions: {
      ecmaVersion: 2020,
      sourceType: "module",
      globals: {
        ...globals.browser,
        ...globals.jquery,
        wp: "readonly",
        fpReservations: "readonly"
      }
    },
    rules: {
      ...js.configs.recommended.rules,
      "no-unused-vars": ["warn", { "args": "none", "caughtErrors": "none", "varsIgnorePattern": "^_" }],
      "no-useless-escape": "off"
    }
  }
];
