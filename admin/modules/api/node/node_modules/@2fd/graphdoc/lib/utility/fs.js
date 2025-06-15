"use strict";
var __awaiter = (this && this.__awaiter) || function (thisArg, _arguments, P, generator) {
    return new (P || (P = Promise))(function (resolve, reject) {
        function fulfilled(value) { try { step(generator.next(value)); } catch (e) { reject(e); } }
        function rejected(value) { try { step(generator["throw"](value)); } catch (e) { reject(e); } }
        function step(result) { result.done ? resolve(result.value) : new P(function (resolve) { resolve(result.value); }).then(fulfilled, rejected); }
        step((generator = generator.apply(thisArg, _arguments || [])).next());
    });
};
var __generator = (this && this.__generator) || function (thisArg, body) {
    var _ = { label: 0, sent: function() { if (t[0] & 1) throw t[1]; return t[1]; }, trys: [], ops: [] }, f, y, t, g;
    return g = { next: verb(0), "throw": verb(1), "return": verb(2) }, typeof Symbol === "function" && (g[Symbol.iterator] = function() { return this; }), g;
    function verb(n) { return function (v) { return step([n, v]); }; }
    function step(op) {
        if (f) throw new TypeError("Generator is already executing.");
        while (_) try {
            if (f = 1, y && (t = y[op[0] & 2 ? "return" : op[0] ? "throw" : "next"]) && !(t = t.call(y, op[1])).done) return t;
            if (y = 0, t) op = [0, t.value];
            switch (op[0]) {
                case 0: case 1: t = op; break;
                case 4: _.label++; return { value: op[1], done: false };
                case 5: _.label++; y = op[1]; op = [0]; continue;
                case 7: op = _.ops.pop(); _.trys.pop(); continue;
                default:
                    if (!(t = _.trys, t = t.length > 0 && t[t.length - 1]) && (op[0] === 6 || op[0] === 2)) { _ = 0; continue; }
                    if (op[0] === 3 && (!t || (op[1] > t[0] && op[1] < t[3]))) { _.label = op[1]; break; }
                    if (op[0] === 6 && _.label < t[1]) { _.label = t[1]; t = op; break; }
                    if (t && _.label < t[2]) { _.label = t[2]; _.ops.push(op); break; }
                    if (t[2]) _.ops.pop();
                    _.trys.pop(); continue;
            }
            op = body.call(thisArg, _);
        } catch (e) { op = [6, e]; y = 0; } finally { f = t = 0; }
        if (op[0] & 5) throw op[1]; return { value: op[0] ? op[1] : void 0, done: true };
    }
};
Object.defineProperty(exports, "__esModule", { value: true });
var path = require("path");
var fs = require("fs");
var fse = require("fs-extra");
var Bluebird = require("bluebird");
/**
 * resolve
 *
 * transform a path relative to absolute, if relative
 * path start with `graphdoc/` return absolute path to
 * plugins directory
 */
var MODULE_BASEPATH = 'graphdoc/';
function resolve(relative) {
    if (relative.slice(0, MODULE_BASEPATH.length) === MODULE_BASEPATH)
        return path.resolve(__dirname, '../../', relative.slice(MODULE_BASEPATH.length));
    return path.resolve(relative);
}
exports.resolve = resolve;
/**
 * Execute fs.read as Promise
 */
exports.readFile = Bluebird.promisify(fs.readFile);
exports.writeFile = Bluebird.promisify(fs.writeFile);
exports.copyAll = Bluebird.promisify(fse.copy);
exports.readDir = Bluebird.promisify(fs.readdir);
exports.mkDir = Bluebird.promisify(fs.mkdir);
exports.removeBuildDirectory = Bluebird.promisify(fse.remove);
/**
 * Create build directory from a templete directory
 */
function createBuildDirectory(buildDirectory, templateDirectory, assets) {
    return __awaiter(this, void 0, void 0, function () {
        var files;
        return __generator(this, function (_a) {
            switch (_a.label) {
                case 0: return [4 /*yield*/, exports.readDir(templateDirectory)];
                case 1:
                    files = _a.sent();
                    return [4 /*yield*/, Bluebird.all(files
                            .filter(function (file) { return path.extname(file) !== '.mustache'; })
                            .map(function (file) { return exports.copyAll(path.resolve(templateDirectory, file), path.resolve(buildDirectory, file)); }))];
                case 2:
                    _a.sent();
                    // create assets directory
                    return [4 /*yield*/, exports.mkDir(path.resolve(buildDirectory, 'assets'))];
                case 3:
                    // create assets directory
                    _a.sent();
                    return [4 /*yield*/, Bluebird.all(assets
                            .map(function (asset) { return exports.copyAll(asset, path.resolve(buildDirectory, 'assets', path.basename(asset))); }))];
                case 4:
                    _a.sent();
                    return [2 /*return*/];
            }
        });
    });
}
exports.createBuildDirectory = createBuildDirectory;
