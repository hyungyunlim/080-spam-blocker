/**
 * OutputInterface
 */
export interface OutputInterface {
    
    log(...obj: Array<any>): void;
    log(msj: string, ...obj: Array<any>): void;
    
    error(...obj: Array<any>): void;
    error(msj: string, ...obj: Array<any>): void;
}

export interface FormatterInterface {
    
    format(str: string, ...replacements: Array<any>): string;
}

/**
 * InputInterface
 */
export interface InputInterface<F, P>{

    argv: Array<string>;

    exec: Array<string>;

    flags: F;

    params: P;
}

/**
 * FlagInterface
 */
export interface FlagInterface<F> {

    name: string;

    description: string;

    list: Array<string>;

    after(input: InputInterface<F , any>, output: OutputInterface): void;

    before(input: InputInterface<F, any>, output: OutputInterface): void;

    parse(flag: string, input: InputInterface<F, any>, output: OutputInterface): void;
}

/**
 * ParamInterface
 */
export interface ParamInterface<P> {

    definition: string;

    after(input: InputInterface<any , P>, output: OutputInterface): void;

    before(input: InputInterface<any, P>, output: OutputInterface): void;

    parse(param: string, input:InputInterface<any, P>, output:OutputInterface): void;
}

/**
 * CommandInterface
 */
export interface CommandInterface<F, P> {

    description: string;

    handle(input: InputInterface<F, P>, output: OutputInterface): void;
}

/**
 * QuickCommandType
 */
export type QuickCommandType = (input: InputInterface<any, any>, output: OutputInterface) => void;

/**
 * CommandType
 */
export type CommandType = string | QuickCommandType | CommandInterface<any, any>;
