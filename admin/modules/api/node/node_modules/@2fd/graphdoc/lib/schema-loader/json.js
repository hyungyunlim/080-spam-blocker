"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
var path_1 = require("path");
exports.jsonSchemaLoader = function (options) {
    try {
        var schemaPath = path_1.resolve(options.schemaFile);
        var introspection = require(schemaPath);
        return Promise.resolve(introspection.data.__schema);
    }
    catch (err) {
        return Promise.reject(err);
    }
};
