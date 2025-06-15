"use strict";
var __extends = (this && this.__extends) || (function () {
    var extendStatics = Object.setPrototypeOf ||
        ({ __proto__: [] } instanceof Array && function (d, b) { d.__proto__ = b; }) ||
        function (d, b) { for (var p in b) if (b.hasOwnProperty(p)) d[p] = b[p]; };
    return function (d, b) {
        extendStatics(d, b);
        function __() { this.constructor = d; }
        d.prototype = b === null ? Object.create(b) : (__.prototype = b.prototype, new __());
    };
})();
Object.defineProperty(exports, "__esModule", { value: true });
var utility_1 = require("../lib/utility");
var navigation_schema_1 = require("./navigation.schema");
var navigation_scalar_1 = require("./navigation.scalar");
var navigation_enum_1 = require("./navigation.enum");
var navigation_interface_1 = require("./navigation.interface");
var navigation_union_1 = require("./navigation.union");
var navigation_object_1 = require("./navigation.object");
var navigation_input_1 = require("./navigation.input");
var navigation_directive_1 = require("./navigation.directive");
var document_schema_1 = require("./document.schema");
var document_require_by_1 = require("./document.require-by");
var NavigationDirectives = (function (_super) {
    __extends(NavigationDirectives, _super);
    function NavigationDirectives(document, graphdocPackage, projectPackage) {
        var _this = _super.call(this, document, graphdocPackage, projectPackage) || this;
        _this.plugins = [
            new navigation_schema_1.default(document, graphdocPackage, projectPackage),
            new navigation_scalar_1.default(document, graphdocPackage, projectPackage),
            new navigation_enum_1.default(document, graphdocPackage, projectPackage),
            new navigation_interface_1.default(document, graphdocPackage, projectPackage),
            new navigation_union_1.default(document, graphdocPackage, projectPackage),
            new navigation_object_1.default(document, graphdocPackage, projectPackage),
            new navigation_input_1.default(document, graphdocPackage, projectPackage),
            new navigation_directive_1.default(document, graphdocPackage, projectPackage),
            new document_schema_1.default(document, graphdocPackage, projectPackage),
            new document_require_by_1.default(document, graphdocPackage, projectPackage),
        ];
        return _this;
    }
    NavigationDirectives.prototype.getNavigations = function (buildForType) {
        return utility_1.Plugin.collectNavigations(this.plugins, buildForType);
    };
    ;
    NavigationDirectives.prototype.getDocuments = function (buildForType) {
        return utility_1.Plugin.collectDocuments(this.plugins, buildForType);
    };
    ;
    NavigationDirectives.prototype.getHeaders = function (buildForType) {
        return utility_1.Plugin.collectHeaders(this.plugins, buildForType);
    };
    NavigationDirectives.prototype.getAssets = function () {
        return utility_1.Plugin.collectAssets(this.plugins);
    };
    return NavigationDirectives;
}(utility_1.Plugin));
exports.default = NavigationDirectives;
