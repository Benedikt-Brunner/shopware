import template from './sw-flow-detail-flow-executions.html.twig';
import './sw-flow-detail-flow-executions.scss';

const { Mixin, Data: { Criteria }, Component } = Shopware;
const { mapState } = Component.getComponentHelper();

/**
 * @private
 * @package services-settings
 */
export default {
    template,

    compatConfig: Shopware.compatConfig,

    inject: ['acl', 'repositoryFactory'],

    emits: ['on-update-total'],

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('listing'),
    ],

    props: {
        searchTerm: {
            type: String,
            required: false,
            default: '',
        },
    },

    data() {
        return {
            sortBy: 'createdAt',
            sortDirection: 'DESC',
            total: 0,
            isLoading: false,
            flowExecutions: null,
            selectedItems: [],
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    computed: {
        flowExecutionRepository() {
            return this.repositoryFactory.create('flow_execution');
        },

        flowExecutionCriteria() {
            const criteria = new Criteria(this.page, this.limit);

            if (this.searchTerm) {
                criteria.setTerm(this.searchTerm);
            }

            criteria
                .addSorting(Criteria.sort(this.sortBy, this.sortDirection))
                .addSorting(Criteria.sort('updatedAt', 'DESC'));

            return criteria;
        },

        flowExecutionColumns() {
            return [
                {
                    property: 'successful',
                    label: this.$tc('sw-flow.detail.executions.list.labelColumnSuccessful'),
                    width: '80px',
                    sortable: true,
                },
                {
                    property: 'flowName',
                    dataIndex: 'name',
                    label: this.$tc('sw-flow.detail.executions.list.labelColumnName'),
                    allowResize: true,
                    routerLink: 'sw.flow.detail',
                    primary: true,
                },
                {
                    property: 'errorMessage',
                    label: this.$tc('sw-flow.detail.executions.list.labelColumnErrorMessage'),
                    allowResize: true,
                    multiLine: true,
                },
                {
                    property: 'date',
                    label: this.$tc('sw-flow.detail.executions.list.labelColumnDate'),
                    allowResize: true,
                    sortable: true,
                },
            ];
        },

        detailPageLinkText() {
            if (this.acl.can('flow.viewer')) {
                return this.$tc('global.default.view');
            }

            return this.$tc('global.default.view');
        },

        assetFilter() {
            return Shopware.Filter.getByName('asset');
        },

        ...mapState('swFlowState', ['triggerEvents']),
    },

    watch: {
        searchTerm(value) {
            this.onSearch(value);
        },
    },

    created() {
        this.createComponent();
    },

    methods: {
        createComponent() {
            this.getList();
        },

        getList() {
            this.isLoading = true;

            this.flowExecutionRepository.search(this.flowExecutionCriteria)
                .then((data) => {
                    this.total = data.total;
                    this.flows = data;
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },

        selectionChange(selection) {
            this.selectedItems = Object.values(selection);
        },
    },
};
