# @2fd/command [![Build Status](https://travis-ci.org/2fd/command.svg?branch=master)](https://travis-ci.org/2fd/command)

Modular command line interface

## install

```bash
    npm install --save @2fd/command
```

## Node Compatibility

* node v6
* node v5
* node v4
* node v0.12

## Goals

- **Lazy load.**

    Commands can be defined as a string that represents its location,
    which allows to generate complex tools without the large number of
    files slowing down the boot.

- **Commands are user-defined.**

    - The name that invokes a command is defined by the implementation,
      which allows you to specify more practical commands
      (such as 'migration:init'or 'mgt:i' instead of 'module-name:migracions:init').

    - Third-party packages can only suggest the list of commands and names
      that implement them, but always you can implement only those you need
      and even replace them with your own ones.

- **Fully modular.**
    A simple and completely object-oriented APIs allow easily:

    - Create commands that can be shared with the community

    - Re-implement and/or expand any functionality

    - Integrate commands created by third parties
    
- **Definitions included**

    If you program in Typescript, definitions are included in the package
    so they are available when you install as dependincie with `npm` without
    requiring a definitions manager as `typings` or `tsd`
    

## Usage

Command implementation

```typescript

    import {
        Command,
        CommandInterface,
        BooleanFlag,
        Param
    } from '@2fd/command';

    // Object command implementation
    class MyCommand extends Command implements CommandInterface {

        description = 'My Command description';

        params = new Param('requireParam [optionalParam] [...optionalParamList]');

        flags = [
            new BooleanFlag('force', ['--force', '-f'], 'Force flag'),
        ];

        // action(input, output) { }; in javascript
        action(input: InputInterface, output: OutputInterface): void {

            // input.params.requireParam: string
            // input.params.optionalParam?: string
            // input.params.optionalParamList: Array<string>
            // input.flags.force: boolean
        };
    }

    export let myCommand = new MyCommand;

    // Function command implementation
    export function myQuickCommand(input: InputInterface, output: OutputInterface): void {
        // Do something
    }

```

Multiple command executor

```javascript

    // cmd.js

    import {
        ExecutorCommand,
        ArgvInput,
        ConsoleOutput
    } from '@2fd/command';

    import {myCommand} from './path/to/commands';

    let tool = new ExecutorCommand();

    tool.version = '1.0.0';
    tool.description = 'Command description';

    tool.addCommand('run', myCommand );
    // or
    tool.addCommand('run', './path/to/commands#myCommand' );

    tool.addCommands({
        'command1' : './path/to/commands#myCommand',
        'command2' : './path/to/commands#myCommand'
    });

    tool.addCommadsNS('ns', {
        'command3' : './path/to/commands#myCommand',
        'command4' : './path/to/commands#myCommand'
    });

    tool.handle(
        new ArgvInput(process.argv),
        new ConsoleOutput()
    );

```

```bash
    > node cmd.js

        Command description [v1.0.0]

        Usage: node cmd.js [COMMAND]

        run            My Command description
        command1       My Command description
        command2       My Command description
        ns:command3    My Command description
        ns:command4    My Command description

```

Simple command in file

```javascript

    // command.js

    import {myCommand} from './path/to/commands';

    myCommand.handle(
        new ArgvInput(process.argv),
        new ConsoleOutput()
    );

```

```bash

    > node command.js --help

        Usage: node command.js [OPTIONS] requireParam [optionalParam] [...optionalParamList]

        My Command description

        --force, -f    Force flag
        --help, -h     Print this help

```