import { NestedTreeControl } from '@angular/cdk/tree';
import { Component, Input, OnInit, HostListener, Output, EventEmitter } from '@angular/core';
import { MatTreeNestedDataSource } from '@angular/material/tree';
import { SortPipe } from '../../plugins/sorting.pipe';
import { UntypedFormControl } from '@angular/forms';
import { tap, debounceTime } from 'rxjs/operators';
import { LatinisePipe } from 'ngx-pipes';
import { FunctionsService } from '@service/functions.service';


/** Flat node with expandable and level information */
interface ExampleFlatNode {
    expandable: boolean;
    item: string;
    parent_id: string;
    level: number;
}

/**
 * @title Tree with flat nodes
 */
@Component({
    selector: 'app-maarch-flat-tree',
    templateUrl: 'maarch-flat-tree.component.html',
    styleUrls: ['maarch-flat-tree.component.scss'],
    providers: [SortPipe],
})
export class MaarchFlatTreeComponent implements OnInit {

    @Input() rawData: any = [];

    @Input() selectionPropagation: boolean = true;
    @Input() openState: string = '';

    @Output() afterSelectNode = new EventEmitter<any>();
    @Output() afterDeselectNode = new EventEmitter<any>();

    holdShift: boolean = false;

    defaultOpenedNodes: any[] = [];

    treeControl = new NestedTreeControl<any>(node => node.children);
    dataSource = new MatTreeNestedDataSource<any>();

    searchMode: boolean = false;
    searchTerm: UntypedFormControl = new UntypedFormControl('');

    lastSelectedNodeIds: any[] = [];

    pendingChildOf: any = {};
    temp: any = {};

    constructor(
        public functions: FunctionsService,
        private sortPipe: SortPipe,
        private latinisePipe: LatinisePipe,
    ) { }

    @HostListener('document:keydown.Shift', ['$event']) onKeydownHandler(event: KeyboardEvent) {
        if (this.selectionPropagation) {
            this.holdShift = true;
        }
    }
    @HostListener('document:keyup.Shift', ['$event']) onKeyupHandler(event: KeyboardEvent) {
        this.holdShift = false;
    }

    ngOnInit(): void {
        // SAMPLE
        /* this.rawData = [
            {
                id: '46',
                text: 'bonjour',
                parent_id: null,
                icon: 'fa fa-building',
                state: {
                    selected: true,
                }
            },
            {
                id: '42',
                text: 'coucou',
                parent_id: '46',
                icon: 'fa fa-building',
                state: {
                    selected: true,
                }
            },
            {
                id: '41',
                text: 'coucou',
                parent_id: '42',
                icon: 'fa fa-building',
                state: {
                    selected: true,
                }
            },
            {
                id: '1',
                text: 'Compétences fonctionnelles',
                parent_id: null,
                icon: 'fa fa-building',
                state: {
                    selected: true,
                }
            },
            {
                id: '232',
                text: 'Compétences technique',
                parent_id: null,
                icon: 'fa fa-building',
                state: {
                    selected: true,
                }
            }
        ]; */
        if (this.rawData.length > 0) {
            this.initData();
        }
    }

    initData(data: any = this.rawData) {
        this.rawData = data;

        // Catch all nodes Ids to open tree nodes in root level
        if (this.openState !== 'all') {
            this.setDefaultOpened();
        }

        this.rawData = data.map((item: any) => ({
            ...item,
            parent_id: item.parent_id === '#' || item.parent_id === '' ? null : item.parent_id,
            state: item.state !== undefined ? {
                selected: item.state.selected,
                opened: item.state.opened || this.defaultOpenedNodes.indexOf(item.id) > -1 || this.openState === 'all',
                disabled: item.state.disabled,
            } : {
                selected: false,
                opened: this.defaultOpenedNodes.indexOf(item.id) > -1 || this.openState === 'all',
                disabled: false,
            }
        }));

        // order data
        this.rawData = this.sortPipe.transform(this.rawData, 'text');

        // Convert flat data to nested data
        const flatToNestedObj = require('flat-to-nested');

        const flatToNested = new flatToNestedObj({
            id: 'id',
            parent: 'parent_id',
            children: 'children',
            options : { deleteParent: false }
        });
        let nestedData = flatToNested.convert(this.rawData);
        nestedData = nestedData.children;

        // Set last child of children nodes to fix css tree lines
        this.initLastNodes(nestedData);

        // Set data to tree
        /**
         * In case where we have a single root node with no parent
         * it becomes the only nested root data
         * instead of taking all root nodes without checking if they have parent nodes
         **/
        const parentNode: any[] = this.rawData.filter((item: any) => this.functions.empty(item.parent_id) || item.parent_id === '#');
        if (parentNode.length === 1) {
            nestedData = parentNode;
        }
        this.dataSource.data = nestedData;
        this.treeControl.dataNodes = nestedData;

        // Set search filter
        this.searchTerm.valueChanges
            .pipe(
                debounceTime(300),
                // filter(value => value.length > 2),
                tap((filterValue: any) => {
                    filterValue = filterValue.trim(); // Remove whitespace
                    filterValue = filterValue.toLowerCase(); // MatTableDataSource defaults to lowercase matches
                    this.searchNode(this.dataSource.data, filterValue);
                }),
            ).subscribe();
    }

    setDefaultOpened() {
        this.rawData.filter((item: any) => item.state !== undefined && item.state.opened).forEach((item: any) => {
            this.defaultOpenedNodes = this.defaultOpenedNodes.concat(this.getParents([item]));
        });
        this.defaultOpenedNodes = this.defaultOpenedNodes.map((node: any) => node.id);
    }

    getData(id: any) {
        return this.rawData.filter((elem: any) => elem.id === id)[0];
    }

    getIteration(it: number) {
        return Array(it).fill(0).map((x, i) => i);
    }

    hasChild = (_: number, node: any) => !!node.children && node.children.length > 0;

    selectNode(node: any) {
        if (!node.state.disabled) {
            if (this.searchMode) {
                this.searchMode = false;
                this.searchTerm.setValue('');
            }

            this.lastSelectedNodeIds = [];

            if (this.holdShift) {
                this.toggleNode(
                    this.dataSource.data,
                    {
                        selected: !node.state.selected,
                        opened: true
                    },
                    [node.id]
                );
            } else {
                node.state.selected = !node.state.selected;
                this.lastSelectedNodeIds = [node];
            }

            if (node.state.selected) {
                this.afterSelectNode.emit(this.lastSelectedNodeIds);
            } else {
                this.afterDeselectNode.emit(this.lastSelectedNodeIds);
            }
        }
    }

    toggleNode(data, state, nodeIds) {
        // traverse throuh each node
        if (Array.isArray(data)) { // if data is an array
            data.forEach((d) => {
                if (nodeIds.indexOf(d.id) > -1 || (this.holdShift && nodeIds.indexOf(d.parent_id) > -1)) {
                    Object.keys(state).forEach(key => {
                        if (d.state.disabled && key === 'opened') {
                            d.state[key] = state[key];
                        } else if (!d.state.disabled) {
                            d.state[key] = state[key];
                            if (key === 'selected') {
                                this.lastSelectedNodeIds.push(d);
                            }
                        }
                    });
                }
                if (this.holdShift && nodeIds.indexOf(d.parent_id) > -1) {
                    nodeIds.push(d.id);
                }

                this.toggleNode(d, state, nodeIds);

            }); // call the function on each item
        } else if (data instanceof Object) { // otherwise, if data is an object
            (data.children || []).forEach((f) => {
                if (nodeIds.indexOf(f.id) > -1 || (this.holdShift && nodeIds.indexOf(f.parent_id) > -1)) {
                    Object.keys(state).forEach(key => {
                        if (f.state.disabled && key === 'opened') {
                            f.state[key] = state[key];
                        } else if (!f.state.disabled) {
                            f.state[key] = state[key];
                            if (key === 'selected') {
                                this.lastSelectedNodeIds.push(f);
                            }
                        }
                    });
                }
                if (this.holdShift && nodeIds.indexOf(f.parent_id) > -1) {
                    nodeIds.push(f.id);
                }
                this.toggleNode(f, state, nodeIds);

            }); // and call function on each child
        }
    }

    getParents(node: any[]) {
        const res = this.rawData.filter((data: any) => data.id === node[node.length - 1].parent_id);

        if (res.length > 0) {
            node.push(res[0]);
            return this.getParents(node);
        } else {
            return node;
        }
    }

    searchNode(data, term) {
        this.searchMode = term !== '';
        // traverse throuh each node
        if (Array.isArray(data)) { // if data is an array
            data.forEach((d) => {
                d.state.opened = true;
                if (this.latinisePipe.transform(d.text.toLowerCase()).indexOf(this.latinisePipe.transform(term)) > -1) {
                    d.state.search = true;
                } else if (term === '') {
                    delete d.state.search;
                } else {
                    d.state.search = false;
                }
                this.searchNode(d, term);

            }); // call the function on each item
        } else if (data instanceof Object) { // otherwise, if data is an object
            (data.children || []).forEach((f) => {
                f.state.opened = true;
                if (this.latinisePipe.transform(f.text.toLowerCase()).indexOf(this.latinisePipe.transform(term)) > -1) {
                    f.state.search = true;
                } else if (term === '') {
                    delete f.state.search;
                } else {
                    f.state.search = false;
                }
                this.searchNode(f, term);

            }); // and call function on each child
        }
    }

    initLastNodes(data) {
        // traverse throuh each node
        if (Array.isArray(data)) { // if data is an array
            data.forEach((d, index) => {
                if (index === data.length - 1) {
                    d.last = true;
                }
                this.initLastNodes(d);
            }); // call the function on each item
        } else if (data instanceof Object) { // otherwise, if data is an object
            (data.children || []).forEach((f, index) => {
                if (index === data.children.length - 1) {
                    f.last = true;
                }
                this.initLastNodes(f);
            }); // and call function on each child
        }
    }

    getSelectedNodes() {
        return this.rawData.filter((data: any) => data.state.selected);
    }
}
