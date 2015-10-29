# Extractor configuration
---

# Basics

- The extractor configuration has 2 parts - `api` and `config`
- The `api` section defines the API behavior such as authentication method, pagination, API's base URI etc
- The `config` section should contain actual authentication information (tokens etc), as well as individual endpoints in the `jobs` section

# API Definition

## baseUrl

The most important part of configuration, the API url (should end with a `/`)
- **Must** be either a string or user function (allows custom domains, see examples)

Example:

    `https://yourDomain.zendesk.com/api/v2/`

-- OR --

    {
        "api": {
            "function": "concat",
            "args": [
                "https://",
                { "attr": "domain" },
                ".zendesk.com/api/v2/"
            ]
        },
        "config": {
            "domain": "yourDomain"
        }
    }

- for *https://yourDomain.zendesk.com/api/v2/*
- uses `config` part, where attribute **domain** would contain `yourDomain`

## http.requiredHeaders

- Headers required to be set in the config section
- Should be an array, eg: `App-Key,X-User-Email`
- **http.headers.{Header-Name}** attribute in config section (eg: `http.headers.App-Key`)

		api:
			http:
				requiredHeaders:
					- App-Key
					- X-User-Email
		config:
			http:
				headers:
					App-Key: asdf1234
					X-User-Email: some@email.com

## http.headers.{Header-Name}

- Headers to be sent with all requests from all configurations
- eg: **http.headers.Accept-Encoding**: `gzip`

# Authentication
## Methods
### basic

- **authentication.type**: `basic`
- use **username** and **password** attributes in the config section

		api:
			authentication:
				type: basic
		config:
			username: whoever
			password: soSecret

### url.query

- Supports signature function as a value
- Values should be described in `api` section
- Example bucket attributes:

- **authentication.type**: `url.query`
- **query.apiKey**: `{"attr": "apiKey"}`
    - this will look for the *apiKey* query parameter value in the config attribute named *apiKey*
- **query.sig**: `{"function":"md5","args":[{"function":"concat","args":[{"attr":"apiKey"},{"attr":"secret"},{"function":"time"}]}]}`
    - this will generate a *sig* parameter value from MD5 of merged configuration table attributes *apiKey* and *secret*, followed by current *time()* at the time of the request (time() being the PHP function)
	- Allowed functions are listed below in the *User functions* section

			api:
				authentication:
					type: url.query
				query:
					apiKey:
						attr: apiKey # will assign "asdf1234" to the 'apiKey' query parameter
					sig:
						function: md5 # will assign result of md5($apiKey.$secret.time()) to 'sig' query parameter
						args:
							-
								function: concat
								args:
									- attr: apiKey
									- attr: secret
									- function: time
			config:
				apiKey: asdf1234
				secret: qwop1290

### login

- Log into a web service to obtain a token, which is then used for signing requests

- **authentication.type**: `login`
- **authentication.loginRequest**: Describe the request to log into the service
    - **endpoint**: `string`
    - **params**: `array`
    - **method**: `string`: [`GET`|`POST`|`FORM`]
    - **headers**: `array`
- **authentication.apiRequest**: Desfines how to use the result from login
    - **headers**: Use values from the response in request headers
        - `[$headerName => $responsePath]`
    - **query**: Use values from the response in request query
        - `[$queryParameter => $responsePath]`
- **authentication.expires**:
    - If set to an integer, the login action will be performed every `n` seconds, where `n` is the value
    - If set to an array, it *must* contain `response` key with its value containing the path to expiry time in the response
        - `relative` key sets whether the expiry value is relative to current time. False by default.

        api:
            authentication:
                type: "login"
                loginRequest:
                    endpoint: "Security/Login"
                    headers:
                        Content-Type: "application/json"
                    method: POST
                    params:
                        UserName:
                            attr: "username"
                        PassWord:
                            attr: "password"
                apiRequest:
                    headers:
                        Ticket: Ticket
        config:
            username: whoever
            password: soSecret

# Pagination
## Methods
Configured in `api.pagination.method`

### offset

- **pagination.method**: `offset`
- **pagination.limit**: integer
    - If a *limit* is set in configuration's **params** field, it will be overriden by its value
- **pagination.limitParam**(optional)
    - sets which query parameter should contain the limit value (default to `limit`)
- **pagination.offsetParam**(optional)
    - sets which query parameter should contain the offset value (default to `offset`)

			api:
				pagination:
					method: offset
					limitParam: limit # default, can be omitted
					offsetParam: offset # default, can be omitted
- **pagination.firstPageParams**(optional)
	- Whether or not include limit and offset params in the first request (default to `true`)

### response.param

- **pagination.method**: `response.param`
- **pagination.responseParam**:
	- path within response that points to a value used for scrolling
	- pagination ends if the value is empty
- **pagination.queryParam**:
	- request parameter to set to the value from response
- **pagination.includeParams**: `false`
	- whether params from job configuration are used in next page request
- **pagination.scrollRequest**:
	- can be used to override settings (endpoint, method, ...) of the initial request

			api:
				pagination:
					method: response.param
					responseParam: _scroll_id
					queryParam: scroll_id
					scrollRequest:
						endpoint: _search/scroll
						method: GET
						params:
							scroll: 1m

### response.url

- **pagination.method**: `response.url`
- **pagination.urlKey**: `next_page`
    - path within response object that points to the URL
    - if value of that key is empty, pagination ends
- **pagination.includeParams**: `false`
	- whether or not to add "params" from the configuration to the URL's query from response
	- if enabled and the next page URL has the same query parameters as the "params" field, values from the "params" are used

			api:
				pagination:
					method: response.url
					urlKey: nextPage
					includeParams: true

### pagenum
simple page number increasing 1 by 1

- **pagination.method**: `pagenum`
- **pagination.pageParam**:(optional) `page` by default
- **pagination.limit**:(optional) integer
    - define the page size
    - if limit is omitted, the pagination will end once an empty page is received. Otherwise it stops once the reply contains less entries than the limit.
- **pagination.limitParam**:(optional)
    - query parameter name to use for *limit*

			api:
				pagination:
					method: pagenum
					pageParam: page
					limit: 500
					limitParam: count # this will result in ?page=12&count=500 query parameters
- **pagination.firstPage**: (optional) `1` by default. Set the first page number.
- **pagination.firstPageParams**(optional)
	- Whether or not include limit and page params in the first request (default to `true`)

# Config

## Metadata
- The extractor loads start time of its previous execution into its metadata. This can then be used in user functions as `time: previousStart`.
- Current execution start is also available at `time: currentStart`.
- This can be used to create incremental exports with minimal overlap, using for example `[start_time: [time: previousStart], end_time: [time: currentStart]]`
- Both values are stored as Unix timestamp. `date` function can be used to convert it.

## Attributes
Attributes must be configured accordingly to the `api` configuration (eg *auth*, *pagination*, *http.requiredHeaders*). They are under the `config` section of the configuration. (see example below)

- **outputBucket**: Name of the bucket to store the output data
- **id**: Optional, if **outputBucket** is set. Otherwise the id is used to generate the output bucket name
- **debug**: If set to `true`, the extractor will output detailed information about it's run, including all API requests. **Warning**, this may reveal your tokens or other sensitive data in the events in your project! It is intended only to help solving issues with configuration.
- **userData**: A set of `key:value` pairs that will be added to the `root` of all endpoints' results
	- Example:

			config:
				userData:
					some: tag
					another: identifier
- **incrementalOutput**: Whether or not to write the result incrementally

## Jobs
- Columns:
    - **endpoint** (required): The API endpoint
    - **params**: Query parameters of the api call, JSON encoded
        - Each parameter in the JSON encoded object may either contain a string, eg: `{""start_date"": ""2014-12-26""}`
        - OR contain an user function as described below, for example to load value from parameters:
        - ```
            {""start_date"":{""function"":""date"",""args"":[""Y-m-d+H:i"",{""function"":""strtotime"",""args"":[{""attr"":""job.1.success""}]}]}}
            ```
    - **dataType**: Type of data returned by the endpoint. It also describes a table name, where the results will be stored
    - **dataField**: Allows to override which field of the response will be exported.
        - If there's multiple arrays in the response "root" the extractor may not know which array to export and fail
        - If the response is an array, the whole response is used by default
        - If there's no array within the root, the path to response data **must** be specified in *dataField*
        - Can contain a path to nested value, dot separater (eg `result.results.products`)
	- **children**: Array of child jobs that use the jobs' results to iterate
		- The endpoint must use a placeholder enclosed in `{}`
		- The placeholder can be prefixed by a number, that refers to higher level of nesting. By default, data from direct parent are used. The direct parent can be referred as `{id}` or `{1:id}`. A "grandparent" result would then be `{2:id}` etc.
		- Results in the child table will contain column(s) containing parent data used in the placeholder(s), prefixed by **parent_**. For example, if your placeholder is `{ticket_id}`, a column **parent_ticket_id** containing the value of current iteration will be appended to each row.
		- **placeholders** array must define each placeholder. It must be a set of `key: value` pairs, where **key** is the placeholder (eg `"1:id"`) and the value is a path within the response object - if nested, use `.` as a separator.
			- Example:

					endpoint: tickets.json
					children:
						-
							endpoint: tickets/{id}/comments.json
							placeholders:
								id: id # the ticket id is in the `id` field
							children:
								-
									endpoint: tickets/{2:ticket_id}/comments/{comment_id}/details.json
									placeholders:
										comment_id: id # the comment id is also in the id field of the comments response
										"2:ticket_id": id # uses the id from tickets.json - 2 levels up

		- **recursionFilter**:
			- Can contain a value consisting of a name of a field from the parent's response, logical operator and a value to compare against. Supported operators are "**==**", "**<**", "**>**", "**<=**", "**>=**", "**!=**"
			- Example: `type!=employee` or `product.value>150`
			- The filter is whitespace sensitive, therefore `value == 100` will look into `value␣` for a `␣100` value, instead of `value` and `100` as likely desired.
	- **method**: GET (default), POST or FORM
	- **responseFilter**: Allows filtering data from API response to leave them from being parsed.
		- Filtered data will be imported as a JSON encoded string.
		- Value of this parameter can be either a string containing path to data to be filtered within response data, or an array of such values.
		- Example:

				{ 'results': [
					{
						'id': 1,
						'data': 'scalar'
					},
					{
						'id': 2
						'data': { 'object': 'can\'t reall parse this!' }
					}
				]}

		- To be able to work with such response, set `"responseFilter": "data"` - it should be a path within each object of the response array, **not** including the key of the response array
		- To filter values within nested arrays, use `"responseFilter": "data.array[].key"`
		- Example:

				{ 'results': [
					{
						'id': 1,
						'data': {
							'array': [
								{ 'key': 'value' }.
								{ 'key': { 'another': 'value' }}
							]
						}
					}
				]}

		- This would be another unparseable object, so the filter above would just convert the `{ 'another': 'value' }` object to a string
		- To filter an entire array, use `array` as the value for *responseFilter*. To filter each array item individually, use `array[]`.
	- **responseFilterDelimiter**: Allows changing delimiter if you need nesting in **responseFilter**, for instance if your data contains keys containing `.`, which is the default delimiter.
		- Example:

				{ 'results': [
					{
						'data.stuff': {
							something: [1,2,3]
						}
					}
				]}

		- Use `'responseFilter': 'data.stuff/something'` together with `'responseFilterDelimiter': '/'` to filter the array in `something`

# Iterations
The configuration can be run multiple times with some (or all) values in `config` section being overwritten. For example, you can run the same configuration for multiple accounts, overriding values of the authentication settings.

**Warning**:
- If you use `userData` in iterations, make sure they all contain the same set of keys!
- Overriding `incrementalOutput` will only use the setting from the **last** iteration that writes to each `outputBucket`

## Example
This way you can download the same data from two different accounts into a single output table, adding the `owner` column to help you recognize which iteration of the config brought in each row in the result.

		api:
			baseUrl: http://example.com/api
			authentication:
				type: basic
		config:
			outputBucket: bunchOfResults
			jobs:
				-
					endpoint: data
		iterations:
			-
				username: chose
				password: potato
				userData:
					owner: "Chose's results"
			-
				username: joann
				password: beer
				userData:
					owner: "Joann's results"

# User functions
Can currently be used in query type authentication or endpoint parameters

## Allowed functions

- `md5`: Generate a md5 key from its argument value
- `sha1`: Generate a sha1 key from its argument value
- `time`: Return time from the beginning of the unix epoch in seconds (1.1.1970)
- `date`: Return date in a specified format
- `strtotime`: Convert a date string to number of seconds from the beginning of the unix epoch
- `base64_encode`
- `hash_hmac`: [See PHP documentation](http://php.net/manual/en/function.hash-hmac.php)
- `sprintf`: [See PHP documentation](http://php.net/manual/en/function.sprintf.php)
- `concat`: Concatenate its arguments into a single string
- `implode`: Concatenate an array from the second argument, using glue string from the first arg

## Syntax
The function must be specified in a YML format, which may contain one of the following 4 objects:

- **String**: `"something"`
- **Function**: One of the allowed functions above
    - Example (this will return current date in this format: `2014-12-08+09:38`:

        ```
		"function": "date",
		"args":
			- "Y-m-d+H:i"
        ```

    - Example with a nested function (will return a date in the same format from 3 days ago):

        ```
		"function": "date",
		"args":
			- "Y-m-d+H:i",
			-
				"function": "strtotime",
				"args": ["3 days ago"]
        ```

- **Config Attribute**: `"attr": "attributeName"` or `"attr": "nested.attribute.name"`
- **Metadata**: `time: previousStart` or `time: currentStart` - only useable in job params.
- **Query parameter**: **TODO**

# Example configuration

		parameters:
			api:
				baseUrl: '{"function":"concat","args":["https://",{"attr":"domain"},".zendesk.com/api/v2/"]}'
				authentication:
					type: basic
				pagination:
					method: response.url
				name: zendesk
			config:
				id: test_docker
				domain: yours
				username: you@wish.com/token
				password: ohIdkSrsly
				jobs:
					-
						endpoint: exports/tickets.json
						params:
							start_time:
								time: previousStart
							end_time:
								function: strtotime
								args:
									- 2015-07-20 00:00
						dataType: tickets_export
						dataField: ''
		                children:
		                    -
		                        endpoint: tickets/{id}/comments.json
		                        recursionFilter: "status!=Deleted"
		                        dataType: comments
		                        placeholders:
		                            # placeholder: path in data
		                            id: id
					-
						endpoint: users.json
						params: {}
						dataType: users
						dataField: ''
					-
						endpoint: tickets.json
						params: {}
						dataType: tickets
						dataField: ''

