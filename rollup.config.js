import Vue from "rollup-plugin-vue"
import path from "path"
import serve from "rollup-plugin-serve"
import resolve from "rollup-plugin-node-resolve"
import babel from "rollup-plugin-babel"
import commonjs from "@rollup/plugin-commonjs"
import builtins from "@erquhart/rollup-plugin-node-builtins"
import globals from "rollup-plugin-node-globals"
import json from "@rollup/plugin-json"


const dev = process.env.DEV == "true"

export default {
    input: path.resolve(__dirname, 'js/web/app.js'),
    output: {
        name: "richdocuments.js",
        format: "amd",
        file : "js/web/richdocuments.js"
    },
    plugins: [
        Vue(),
        resolve({
          mainFields: ["browser", "jsnext", "module", "main"],
          include: "node_modules/**",
          preferBuiltins: true
        }),
        dev && serve({
            contentBase: ["js/web"],
            port: process.env.PORT || 5566
        }),
        babel({
          exclude: "node_modules/**",
          runtimeHelpers: true
        }),
        commonjs({
          include: "node_modules/**"
        }),
        globals(),
        builtins(),
        json(),
    ]
}