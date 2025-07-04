var gqliurl = '/graphql';
var gqliheaders = {
	'Accept': 'application/json',
	'Content-Type': 'application/json',
}

gqliurl = host+'/admin/api/api/gql'

/**
 * This GraphiQL example illustrates how to use some of GraphiQL's props
 * in order to enable reading and updating the URL parameters, making
 * link sharing of queries a little bit easier.
 *
 * This is only one example of this kind of feature, GraphiQL exposes
 * various React params to enable interesting integrations.
 */
// Parse the search string to get url parameters.
var search = window.location.search;
var parameters = {};
search.substr(1).split('&').forEach(function (entry) {
	var eq = entry.indexOf('=');
	if (eq >= 0) {
		parameters[decodeURIComponent(entry.slice(0, eq))] =
			decodeURIComponent(entry.slice(eq + 1));
	}
});
// if variables was provided, try to format it.
if (parameters.variables) {
	try {
		parameters.variables =
			JSON.stringify(JSON.parse(parameters.variables), null, 2);
	} catch (e) {
		// Do nothing, we want to display the invalid JSON as a string, rather
		// than present an error.
	}
}
// When the query and variables string is edited, update the URL bar so
// that it can be easily shared
function onEditQuery(newQuery) {
	parameters.query = newQuery;
	updateURL();
}
function onEditVariables(newVariables) {
	parameters.variables = newVariables;
	updateURL();
}
function onEditOperationName(newOperationName) {
	parameters.operationName = newOperationName;
	updateURL();
}
function updateURL() {
	var newSearch = '?' + Object.keys(parameters).filter(function (key) {
		return Boolean(parameters[key]);
	}).map(function (key) {
		return encodeURIComponent(key) + '=' +
			encodeURIComponent(parameters[key]);
	}).join('&');
	history.replaceState(null, null, newSearch);
}
// Defines a GraphQL fetcher using the fetch API. You're not required to
// use fetch, and could instead implement graphQLFetcher however you like,
// as long as it returns a Promise or Observable.
function graphQLFetcher(graphQLParams) {
	// This example expects a GraphQL server at the path /graphql.
	// Change this to point wherever you host your GraphQL server.
	var formData = new FormData();
	formData.append('scopes', $("#scope-explorer").val());
	formData.append('host', host);
	return fetch('ajax.php?module=api&command=getAccessToken', {
		method: 'post',
		body: formData,
		credentials: 'same-origin',
	}).then(function (response) {
		return response.json()
	}).then(function (responseBody) {
		gqliheaders.Authorization = "Bearer "+responseBody.token;
		return fetch(gqliurl, {
			method: 'post',
			headers: gqliheaders,
			body: JSON.stringify(graphQLParams),
			credentials: 'include',
		})
	})
	.then(function (response) {
		return response.text();
	}).then(function (responseBody) {
		try {
			return JSON.parse(responseBody);
		} catch (error) {
			return responseBody;
		}
	});
}

function renderGraphQLi() {
	$("#graphiql-container").html("");
	ReactDOM.render(
		React.createElement(GraphiQL, {
			fetcher: graphQLFetcher,
			query: parameters.query,
			variables: parameters.variables,
			operationName: parameters.operationName,
			onEditQuery: onEditQuery,
			onEditVariables: onEditVariables,
			onEditOperationName: onEditOperationName,
			defaultQuery: "{\n\tversion\n}",
			onToggleDocs: true
		}),
		document.getElementById('graphiql-container')
	);
}

$("#reload-explorer").click(function() {
	if(!$("#scope-explorer").val().length) {
		alert("Please define a valid scope")
		return;
	}
	renderGraphQLi()
});
