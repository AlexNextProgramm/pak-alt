import { ajax } from "./ajax";
import { $, Rocet } from "@rocet/rocet";
import { integ } from "@rocet/integration";
import { RocetElement } from "@rocet/RocetNode";
import { Fire } from "./ui/fire";

interface settingDatabase {
    hideColumsIndex: Array<number>,
    colors: [number, number, number, boolean] | null,
    limitTabPagination: number
    limit: number
    pagingCount: number | null
    filter: any
}

export class Datatable {

    public rows: Function | null = null;
    public table: HTMLTableElement | HTMLDivElement
    public ColumsElements: HTMLTableCellElement[] = []

    public buildCells: Function;
    public buildRows: Function;
    public initCallback: Function | null = null;
    public limitTabPagination = 3;
    public pagination: boolean = false;
    public statusInfo: boolean = false;
    public limitInfo: boolean = false;
    public wrapper: HTMLElement
    public infoFooter: boolean = false;
    public infoHeader: boolean = false;
    public gradateLimitedSelect = [10, 25, 100];
    public countsTab: boolean = false;
    public colors: boolean = false;
    public showColums: boolean = false;
    public rerender: Function| null = null;
    public addElementHeader: Array<Function> = [];
    public isDataTableScrolling: boolean = false;
    public isSaveSetting: boolean = false;

    public settings: settingDatabase = {
        hideColumsIndex: [],
        colors: null,
        limitTabPagination: 3,
        limit: 10,
        pagingCount: null,
        filter: null
    }

    private page: {
        count: number
        limit: number
        all: number
    }

    private dataSend: {
        table: {
            search: any
            pages: any
        }
    }


    constructor(table: HTMLTableElement | string) {
        if (typeof table == "string") {
            const el = document.querySelector(`table[name=${table}]`);
            if (!el || !(el instanceof HTMLTableElement)) {
                throw new Error(`Table with name="${table}" not found or is not an HTMLTableElement`);
            }
            this.table = el;
        } else {
            this.table = table;
        }
        this.isDataTableScrolling = this.table.getAttribute('scrolling') == '1';
        this.isSaveSetting = $(this.table).attr('is-save-setting') == '1';
        this.initWrapper()
        let limit = Number(this.table.getAttribute('limit'))
        this.page = {
            count: 1,
            limit: limit ? limit: 10,
            all: 0
        }
        this.setSettingFilter();
        this.init()
    }

    private setSettingFilter() {
        const setting = this.loadSettingStorage(false);
        if (!setting) return;
        const filter = setting['filter'] || [];
        const names = Object.keys(filter);

        for (let i = 0; i < names.length; i++) {
            let name = names[i];
            let value = filter[name];
            let input = $(this.table).find(`[name="${name}"]`);
            if (value.value && value.value !== null && value.value != '') {
                value = value.value;
            }
            if (typeof value == 'string' || typeof value == 'number') {
                if (['checkbox'].includes(input.attr('type')) && value == 1) {
                    input.prop('checked', true);
                    continue;
                }
                input.val(String(value));
            }
            if (Array.isArray(value) && input.item() instanceof HTMLSelectElement) {
                let select: HTMLSelectElement = input.item() as HTMLSelectElement;
                Array.from(select.options).forEach((option: HTMLOptionElement) => {
                    option.selected = false;
                    value.forEach((v) => {
                        if (String(option.value) == String(v)) {
                            option.selected = true;
                        }
                    })
                });
            }
        }
            this.page.count = Number(this.getUrlParam('page') || 1)
    }

    private setSetting() {
        if (this.loadSettingStorage()) {
            this.limitTabPagination = this.settings.limitTabPagination || 3
            this.page.limit = this.settings.limit || this.page.limit
            
        }
    }
    // построить таблицу
    private init() {
        this.setSetting()
        let gfilter = this.getFilter();
        this.saveSettingStorage('filter', gfilter);
        this.dataSend = {
            table: {
                search: gfilter,
                pages: this.page
            }
        }

        const name = this.table.getAttribute("name");
        this.pagination = this.table.getAttribute('pagination') == '1';
        this.statusInfo = this.table.getAttribute('statusInfo') == '1';
        this.limitInfo = this.table.getAttribute('limitInfo') == '1';
        this.colors = this.table.getAttribute('colors') == '1'
        this.countsTab = this.table.getAttribute('countsTab') == '1'
        this.showColums = this.table.getAttribute('showColums') == '1'
        this.infoHeader = this.table.getAttribute('infoHeader') == '1' || this.limitInfo || this.colors || this.showColums;
        this.infoFooter = this.table.getAttribute('infoFooter') == '1' || this.statusInfo || this.pagination;
        this.isDataTableScrolling = this.table.getAttribute('scrolling') == '1';
        window.datatable = {
            [name]: this
        }

        if (isDevelopment) console.log("DataTable request: ", this.dataSend);

        ajax.post(this.dataSend, { datatable: name }).then((data) => {
            if (isDevelopment) console.log("DataTable response: ", data);

            // Если в ответе есть fire — показываем уведомление и не рендерим таблицу
            if (data?.fire) {
                Fire.show(data.fire);
                this.page.all = 0;
                this.render([]);
                return;
            }

            this.page.all = data.pages.all;
            this.render(data.item);
        })

    }

    private initWrapper() {
        const wrapper = document.createElement('div');
        wrapper.classList.add('datatable-wrapper');
    
        if (this.isDataTableScrolling) {
            const scrolling = document.createElement('div');
            scrolling.classList.add('datatable-wrapper-scrolling');
            wrapper.append(scrolling);
            this.table.parentNode.insertBefore(wrapper, this.table);
            scrolling.append(this.table)
        } else { 
            this.table.parentNode.insertBefore(wrapper, this.table);
            wrapper.appendChild(this.table);
        }
        this.wrapper = wrapper;
    }

    private setHeadersElements() {
        this.ColumsElements = [];
        this.table.querySelector('[name=column]').querySelectorAll('th').forEach((th: HTMLTableCellElement) => {
            if (th.textContent != "") {
                this.ColumsElements.push(th);
            }
        })
    }

   private getFilter(): any {
        this.setHeadersElements();
        const filter = this.table.querySelector('[name=filter]');
        if (!filter || !(filter instanceof HTMLTableRowElement))
            return {}
        const elements: NodeListOf<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement> = filter.querySelectorAll("input, select, textarea");
        const data: any = {};

        elements.forEach((el) => {

            const sign = el.getAttribute("sign");
            const name = el.getAttribute("name");
            if (el instanceof HTMLInputElement) {
                const value = el.value;

                if (name && value) {
                    data[name] = sign ? { sign: sign, value: value } : value;
                }
            }

            if (el instanceof HTMLSelectElement) {
                let value = Array.from(el.selectedOptions).map(option => option.value);
                if (!(value.length == 1 && value[0] === '')) {
                    data[name] = sign ? { sign: sign, value: value } : value;
                }
                
            }
            $(el as HTMLElement).on('change', () => {
                this.setUrlParam('page', null);
                this.init();
            });
        })
        return data;
    }

    static get(name: string): Datatable | null
    {
        if (!(window.datatable && window.datatable[name])) {
            if (isDevelopment)console.warn("not found table " + name);
            return null;
        }
        return window.datatable[name] as Datatable;
    }

   private render(item: any) {

        const colunm = this.table.querySelector("[name=column]")
        const aliasAll = colunm.querySelectorAll("[alias]")
        let tbody: Array<RocetElement> = [];
        const table = new Rocet(this.table.querySelector('tbody'));
        if (!this.rerender) {

            item.forEach((row: any, indexRow: number) => {
                const TR: Array<RocetElement> = []
    
                aliasAll.forEach((el, indexElem: number) => {
                    const alias = el.getAttribute('alias');
                    const noneColumn: boolean = !(this.settings.hideColumsIndex.indexOf(indexElem) !== -1)
                    let result: RocetElement = <td className={noneColumn ? "" : "d-none"}>{String(row[alias])}</td>
                    if (this.buildCells) {
                        const rowBuild = this.buildCells(row, alias, indexRow, indexElem)
                        result = rowBuild || result;
                    }
    
                    TR.push(result)
                });
                let Rows: RocetElement = <tr>{...TR}</tr>
                if (this.buildRows) {
                    const rows = this.buildRows(row, TR, indexRow)
                    Rows = rows || Rows
                }
                tbody.push(Rows);
            });
           
            if (tbody.length == 0) {
                const colCount = aliasAll.length || 1;
                tbody.push(
                    <tr>
                        <td colspan={String(colCount)} className="not-found">
                            По вашему запросу ничего не найдено
                        </td>
                    </tr>
                );
            }
        } else { 
            tbody = this.rerender(item, aliasAll);
        }

       table.render(() => {
           return <tbody>{...tbody}</tbody>
       });

       if (this.settings?.hideColumsIndex) {
           $(this.table).find('thead > tr').each(($tr: Rocet) => {
               $tr.find('th').each(($th: Rocet, i: number) => {
                   if (this.settings.hideColumsIndex.indexOf(i) !== -1) {
                       $th.classAdd('d-none');
                   }
               })
           })
       }

        if (this.infoFooter) {
            this.initInfoFooter();
        }
        if (this.infoHeader) {
            this.initInfoHeader()
        }
        if (this.settings.colors) {
            this.setColors(this.settings.colors)
        }
        if (this.initCallback) {
            this.initCallback(this);
        }
    }
    private initInfoHeader() {
        let $info: Rocet = $(this.wrapper.querySelector(".info-header-table"));
        if ($info.length == 0) {
            $info = $(<div className="info-header-table"></div>);
            this.wrapper.prepend($info.item());
        } else { 
            return;
        }
        const limitedSelect = this.initLimitInfo();
        $info.add(<div className="limited-select">{limitedSelect}</div>);

        let colors: JSX.Element;
        if (this.colors) {
            colors = <div className="colors"><p>colors</p><colors onclick={(evt: any) => this.eventColors(evt, this)}></colors></div>
            $info.add(colors)
        }

        let colums: JSX.Element;
        const select: Array<JSX.Element> = []
        if (this.showColums) {
            this.ColumsElements.forEach((th: HTMLTableCellElement, i) => {
                let ischeck = !(this.settings.hideColumsIndex.indexOf(i) !== -1)
                select.push(<label className={ischeck ? 'checked' : ''}>{th.textContent}<input class="d-none" type="checkbox"  data-value={String(i)} checked={ischeck} onclick={(evt: any) => this.eventShowColums(evt, this)}></input></label>)
            })
            
            $info.add(<div className="selectT" tabindex="1">
                    <label className="selectT-label" onclick={selectT}>Видимость столбцов</label>
                    <div className="selectT-content">{...select}</div>
                </div>
            );
            function selectT(ev:MouseEvent) { 
                const btn = $(this);
                btn.closest('.selectT').find('.selectT-content').classToggle('selectT-active')
            }
            $info.find('label input[type="checkbox"]').on('click', function () {
                if (this.checked) {
                    $(this).closest('label').classAdd('checked');
                } else {
                    $(this).closest('label').classRemove('checked');
                }
            });
            $info.find('.selectT').on('focusout', function () { 
                $(this).find('.selectT-content').classRemove('selectT-active')
            })
        }


        const BlockButtons: Array<JSX.Element> = [];
        if (this.addElementHeader.length != 0) { 
            this.addElementHeader.forEach((fn: Function) => {
                BlockButtons.push(fn(this))
            });
        }
        BlockButtons.push(<button type="button" className="reset" title="Сбросить фильтр" aria-label="Сбросить фильтр" onclick={()=>this.resetFilter()}></button>)
        $info.add(<div className="block-buttons">{...BlockButtons}</div>);
        
        this.eventlimitedInfo();
    }

    private initLimitInfo(): JSX.Element {
        let span: JSX.Element
        if (this.limitInfo) {
            const option: Array<JSX.Element> = []
            this.gradateLimitedSelect.forEach((val: number) => option.push(<option selected={val == this.page.limit}>{String(val)}</option>))

            span = <div className="limited-info">
                <span>Показать</span>
                <select evt="database-limited-info">
                    {...option}
                </select>
                <span>Записей</span>
            </div>;
        }
        return span;
    }

    private initInfoFooter(): HTMLElement {
        const div: HTMLElement = this.wrapper.querySelector(".info-footer-table") ?? document.createElement('div');
        if (!div.classList.contains('info-footer-table')) {
            div.classList.add('info-footer-table');
            this.wrapper.append(div)
        }
        $(div).render((Rocet: Rocet) => {
            return <div className="info-footer-table">
                <div className="status">{...this.statusInit(Rocet)}</div>
                <div className="pagination">{...this.paginationInit(Rocet)}</div>
            </div>
        })
        return div;
    }

    private paginationInit(info: Rocet): Array<RocetElement> {
        const span: Array<JSX.Element> = [];
        if (this.pagination) {

            const stepAll = Math.ceil(this.page.all / this.page.limit)
            if (this.countsTab) {
                const options: Array<JSX.Element> = [];
                [3, 5, 7, 10].forEach((v) => { options.push(<option selected={this.limitTabPagination == v}>{String(v)}</option>) });
                span.push(<div className="count-tabs">
                    <p>Кол. ст</p>
                    <select onchange={(evt: any) => this.eventSelectTabsPages(evt, this)}>{...options}</select></div>)
            }
            span.push(<span className="btn btn-primary" evt="back-pagination">{'<<'}</span>)
            let endLimitTabPagination = (stepAll - this.limitTabPagination) < 0 ? 0 : stepAll - this.limitTabPagination

            // событие начало
            let isSed = false;
            if (this.page.count < this.limitTabPagination) {
                let it = this.limitTabPagination > stepAll ? (stepAll == 0 ? 1 : stepAll) : this.limitTabPagination

                for (let i = 1; i <= it; i++) {
                    span.push(<span className={"btn btn-primary" + (i == this.page.count ? " active " : "")} evt="click-pagination">{String(i)}</span>);
                }
                if (this.limitTabPagination < stepAll && stepAll > 0) {
                    span.push(<span className="btn btn-primary pagination-empty">{'...'}</span>);
                    span.push(<span className="btn btn-primary" evt="click-pagination">{String(stepAll)}</span>);
                }
                isSed = true
            } else if (this.page.count >= this.limitTabPagination && this.page.count <= endLimitTabPagination + 1 && !isSed) {
                span.push(<span className={"btn btn-primary"} evt="click-pagination">{String(1)}</span>);
                span.push(<span className="btn btn-primary pagination-empty">{'...'}</span>);

                for (let i = this.page.count - 1; i <= this.page.count + 1; i++) {
                    span.push(<span className={"btn btn-primary" + (i == this.page.count ? " active " : "")} evt="click-pagination">{String(i)}</span>);
                }
                span.push(<span className="btn btn-primary pagination-empty">{'...'}</span>);
                span.push(<span className="btn btn-primary" evt="click-pagination">{String(stepAll)}</span>);
                isSed = true
            } else if (this.page.count >= endLimitTabPagination && !isSed) {
                if (stepAll > this.limitTabPagination) {
                    span.push(<span className={"btn btn-primary"} evt="click-pagination">{String(1)}</span>);
                    span.push(<span className="btn btn-primary pagination-empty">{'...'}</span>);
                    endLimitTabPagination += 1
                } else {
                    endLimitTabPagination = 1
                }

                for (let i = endLimitTabPagination; i <= stepAll; i++) {
                    span.push(<span className={"btn btn-primary" + (i == this.page.count ? " active " : "")} evt="click-pagination">{String(i)}</span>);
                }
            }

            span.push(<span className="btn btn-primary" evt="next-pagination">{'>>'}</span>)
            info.addExecAll(() => this.paginationEvent(this));
        }
        return span;
    }

    private paginationEvent(datatable: Datatable) {

        const eventElementPagination: NodeListOf<HTMLSpanElement | null> = datatable.wrapper.querySelectorAll('[evt=click-pagination]');
        const eventback: HTMLSpanElement = datatable.wrapper.querySelector('[evt=back-pagination]');
        const eventnext: HTMLSpanElement = datatable.wrapper.querySelector('[evt=next-pagination]');
        eventnext.onclick = () => {
            const limitStepAll = Math.ceil(datatable.page.all / datatable.page.limit)
            datatable.page.count = datatable.page.count >= limitStepAll ? limitStepAll : datatable.page.count + 1
            datatable.setUrlParam('page', datatable.page.count);
            datatable.init();
        }
        eventback.onclick = () => {
            const count = (datatable.page.count - 1) <= 0 ? 1 : (datatable.page.count - 1);
            datatable.page.count = count;
            datatable.setUrlParam('page', datatable.page.count);
            datatable.init();
        }
        eventElementPagination.forEach((span) => {
            span.onclick = (evt) => {
                datatable.page.count = Number(span.textContent);
                datatable.setUrlParam('page', datatable.page.count);
                datatable.init();
            }
        })
    }

    private eventlimitedInfo() {
        const eventLimitedInfo: HTMLSelectElement = this.wrapper.querySelector('[evt=database-limited-info]');
        eventLimitedInfo.onchange = (evt) => {
            const newlimit = Number(eventLimitedInfo.value)
            this.page.count = Math.ceil((this.page.count - 1) * this.page.limit / newlimit)
            this.page.count = this.page.count <= 0 ? 1 : this.page.count
            this.page.limit = newlimit
            this.saveSettingStorage('limit', newlimit);
            this.init();
        }
    }


    private statusInit(Rocet: Rocet): Array<JSX.Element> {

        const status: Array<JSX.Element> = []
        if (this.statusInfo) {
            const countTo = (this.page.count * this.page.limit) > this.page.all ? this.page.all : this.page.count * this.page.limit;
            let countfrom = this.page.all == 0 ? 0 : (this.page.count * this.page.limit) - (this.page.limit - 1)
            status.push(<div className="">{
                `Записи с ${countfrom} 
                по ${(countTo || 0)}
                из ${(this.page.all || 0)}`}</div>)
        }
        return status;
    }

    private eventSelectTabsPages(evt: any, datatable: Datatable) {
        datatable.limitTabPagination = Number(evt.target.value)
        datatable.initInfoFooter()
    }

    private setColors(rgb: [number, number, number, boolean]) {
        const [r, g, b, islight] = rgb;
        this.saveSettingStorage('colors', [r, g, b])
        document.documentElement.style.setProperty('--colors-theme-table', `rgb(${r} ${g} ${b}`);
        document.documentElement.style.setProperty('--colors-theme-table-hover', islight ? `rgb(${r - 20} ${g - 20} ${b - 20})` : `rgb(${r} ${g} ${b} / 90%)`)
        document.documentElement.style.setProperty('--colors-theme-table-font', islight ? "#f9f9f9" : "#111f4e")
        document.documentElement.style.setProperty('--colors-theme-table-font-invert', islight ? "#111f4e" : "#f9f9f9")
        document.documentElement.style.setProperty('--colors-theme-table-line', `rgb(${r} ${g} ${b} / 40%)`)
    }

    private eventColors(evt: any, database: Datatable) {
        evt['rgb'].push(evt['islight'])
        this.saveSettingStorage('colors', evt['rgb'])
        this.setColors(evt['rgb'])
    }

    private eventShowColums(evt: MouseEvent | any, datatable: Datatable) {
        datatable.ColumsElements.forEach((th: HTMLTableCellElement, i: number) => {
            if (i == Number($(evt.target).data('value'))) {
                if (!evt.target.checked) {
                    datatable.hideShowColumn(i, true)
                    datatable.settings.hideColumsIndex.push(i)
                } else {
                    datatable.hideShowColumn(i, false)
                    datatable.settings.hideColumsIndex = datatable.settings.hideColumsIndex.filter((ind: number) => ind !== i);
                }
                datatable.saveSettingStorage('hideColumsIndex', datatable.settings.hideColumsIndex)
            }
        })
    }

    public hideShowColumn(index: number, hideShow: boolean) {
        this.table.querySelectorAll('tr').forEach(row => {
            const cell = row.querySelector(`td:nth-child(${index + 1})`);
            const th = row.querySelector(`th:nth-child(${index + 1})`);
            if (cell) {
                hideShow ? cell.classList.add('d-none') : cell.classList.remove('d-none');
            }
            if (th) {
                hideShow ? th.classList.add('d-none') : th.classList.remove('d-none');
            }
        });
    }

    private loadSettingStorage(isThis:boolean = true) {
        let setting: any = localStorage.getItem(this.table.getAttribute('name'))
        setting = JSON.parse(setting)
        if (setting) {
            if (isThis) {
                this.settings = setting
            } else { 
                return setting;
            }
            return true;
        } else {
            return false;
        }
    }

    public saveSettingStorage(key: keyof settingDatabase, value: any) {
        if (this.isSaveSetting) { 
            this.settings[key] = value;
            localStorage.setItem(this.table.getAttribute('name'), JSON.stringify(this.settings))
        }
    }

    public reload() {
        const name = this.table.getAttribute("name");
        ajax.post(this.dataSend, { datatable: name }).then((data) => {
            if (isDevelopment) console.log("DataTable response: ", data);
            this.page.all = data.pages.all;
            this.render(data.item);
        })
    }

    public resetFilter() {
        $(this.table).find('thead').find('[name]').each(($el: Rocet) => {
            if (['INPUT', "SELECT", "TEXTAREA"].includes($el.tagName)) { 
                $el.val('')
            }
        })
        this.saveSettingStorage('pagingCount', 1);
        this.saveSettingStorage('filter', null);
        this.init();
    }

    public setUrlParam(name:string, value:any) { 
        let params = new URL(window.location.href);
        if (value === null || value === '') {
            params.searchParams.delete(name)
        } else { 
            params.searchParams.set(name, String(value));
        }
        history.replaceState(null, '', params.toString());
    }
    public getUrlParam(name:string) { 
        let params = new URL(window.location.href);
        return params.searchParams.get(name)
    }
}
