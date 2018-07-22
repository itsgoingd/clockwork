class Callgrind
{
	constructor(metadata, functions) {
		this.metadata = metadata
		this.functions = functions
	}

	static parse(data) {
		return new Promise((accept, reject) => {
			accept(Callgrind.parseSync(data))
		})
	}

	static parseSync(data) {
		let metadata = {}
		let functions = []

		let compressedFileNames = {}
		let compressedFunctionNames = {}

		data = data.split("\n")

		let lineNo = 0
		let line, matches, fn, functionId, _
		while ((line = data[lineNo++]) !== undefined) {
			if (line.startsWith('fl=')) {
				let [ _, callLine ] = this.resolveCompressedName(line.match(/fl=(.+)/)[0], compressedFileNames)

				line = data[lineNo++]

				let [ functionId, functionName ] = this.resolveCompressedName(line.match(/fn=(.+)/)[0], compressedFunctionNames)

				line = data[lineNo++]

				let metrics = line.split(' ').map(metric => parseFloat(metric))
				let lineNumber = metrics.shift()

				fn = functions[functionId]

				if (! fn) {
					functions[functionId] = fn = {
						name: functionName,
						file: callLine,
						line: lineNumber,
						invocations: 0,
						self: (new Array(metrics.length)).fill(0),
						inclusive: (new Array(metrics.length)).fill(0),
						callers: [],
						subCalls: []
					}
				}

				fn.invocations++
				fn.self = fn.self.map((metric, index) => metric + metrics[index])
				fn.inclusive = fn.inclusive.map((metric, index) => metric + metrics[index])
			} else if (line.startsWith('cfn=')) {
				let [ calledFunctionId, calledFunctionName ] = this.resolveCompressedName(line.match(/cfn=(.+)/)[0], compressedFunctionNames)

				line = data[lineNo++]
				line = data[lineNo++]

				let metrics = line.split(' ').map(metric => parseFloat(metric))
				let lineNumber = metrics.shift()

				let calledFunction = functions[calledFunctionId]

				fn.inclusive = metrics.map((metric, index) => metric + (fn.inclusive[index] || 0))

				let callerInfo = calledFunction.callers[functionId]

				if (! callerInfo) {
					calledFunction.callers[functionId] = callerInfo = {
						name: fn.name,
						line: lineNumber,
						calls: 0,
						summed: (new Array(metrics.length)).fill(0)
					}
				}

				callerInfo.calls++
				callerInfo.summed = callerInfo.summed.map((metric, index) => metric + metrics[index])

				let subCallInfo = fn.subCalls[calledFunctionId]

				if (! subCallInfo) {
					fn.subCalls[calledFunctionId] = subCallInfo = {
						name: calledFunctionName,
						line: lineNumber,
						calls: 0,
						summed: (new Array(metrics.length)).fill(0)
					}
				}

				subCallInfo.calls++
				subCallInfo.summed = subCallInfo.summed.map((metric, index) => metric + metrics[index])
			} else if (matches = line.match(/^(.+?): (.+)/)) {
				metadata[matches[1]] = matches[2]
			}
		}

		return new Callgrind(metadata, functions.slice(1))
	}

	static resolveCompressedName(input, compressedNames) {
		let [ _, id, name ] = input.match(/\((\d+)\)(?: (.*))?/)

		if (name) compressedNames[id] = name

		return [ id, compressedNames[id] ]
	}
}
