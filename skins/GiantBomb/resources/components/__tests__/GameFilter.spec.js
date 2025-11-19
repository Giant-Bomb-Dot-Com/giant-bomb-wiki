const { mount } = require('@vue/test-utils');
const GameFilter = require('../GameFilter.vue');

describe('GameFilter', () => {
	let wrapper;
	const mockPlatforms = ['PlayStation 5', 'Xbox Series X', 'PC', 'Nintendo Switch'];

	beforeEach(() => {
		// Clear URL parameters before each test
		window.history.replaceState({}, '', window.location.pathname);
	});

	afterEach(() => {
		if (wrapper) {
			wrapper.unmount();
		}
	});

	describe('Initial Render', () => {
		it('renders the filter title', () => {
			wrapper = mount(GameFilter, {
				props: {
					platformsData: JSON.stringify(mockPlatforms),
				},
			});

			expect(wrapper.find('.filter-title').text()).toBe('Filter Games');
		});

		it('renders all filter inputs with correct labels', () => {
			wrapper = mount(GameFilter, {
				props: {
					platformsData: JSON.stringify(mockPlatforms),
				},
			});

			const labels = wrapper.findAll('label');
			expect(labels).toHaveLength(3);
			expect(labels[0].text()).toBe('Search');
			expect(labels[1].text()).toBe('Platform');
			expect(labels[2].text()).toBe('Sort By');
		});

		it('renders search input', () => {
			wrapper = mount(GameFilter, {
				props: {
					platformsData: JSON.stringify(mockPlatforms),
				},
			});

			const searchInput = wrapper.find('#search-filter');
			expect(searchInput.exists()).toBe(true);
			expect(searchInput.element.value).toBe('');
		});

		it('renders platform dropdown with all platforms', async () => {
			wrapper = mount(GameFilter, {
				props: {
					platformsData: JSON.stringify(mockPlatforms),
				},
			});

			await wrapper.vm.$nextTick();

			const platformSelect = wrapper.find('#platform-filter');
			expect(platformSelect.exists()).toBe(true);

			const options = platformSelect.findAll('option');
			expect(options).toHaveLength(mockPlatforms.length + 1); // +1 for "All Platforms"
			expect(options[0].text()).toBe('All Platforms');
			expect(options[1].text()).toBe('PlayStation 5');
			expect(options[2].text()).toBe('Xbox Series X');
			expect(options[3].text()).toBe('PC');
			expect(options[4].text()).toBe('Nintendo Switch');
		});

		it('renders sort dropdown with all sort options', () => {
			wrapper = mount(GameFilter, {
				props: {
					platformsData: JSON.stringify(mockPlatforms),
				},
			});

			const sortSelect = wrapper.find('#sort-filter');
			expect(sortSelect.exists()).toBe(true);

			const options = sortSelect.findAll('option');
			expect(options).toHaveLength(4);
			expect(options[0].text()).toBe('Title (A-Z)');
			expect(options[0].element.value).toBe('title-asc');
			expect(options[1].text()).toBe('Title (Z-A)');
			expect(options[1].element.value).toBe('title-desc');
			expect(options[2].text()).toBe('Newest First');
			expect(options[2].element.value).toBe('date-desc');
			expect(options[3].text()).toBe('Oldest First');
			expect(options[3].element.value).toBe('date-asc');
		});
	});

	describe('Filter Selection', () => {
		it('updates search query and dispatches event', async () => {
			const eventListener = jest.fn();
			window.addEventListener('games-filter-changed', eventListener);

			wrapper = mount(GameFilter, {
				props: {
					platformsData: JSON.stringify(mockPlatforms),
				},
			});

			const searchInput = wrapper.find('#search-filter');
			await searchInput.setValue('zelda');

			expect(eventListener).toHaveBeenCalled();
			expect(eventListener.mock.calls[0][0].detail).toEqual({
				search: 'zelda',
				platform: '',
				sort: 'title-asc',
			});

			window.removeEventListener('games-filter-changed', eventListener);
		});

		it('updates platform filter and dispatches event', async () => {
			const eventListener = jest.fn();
			window.addEventListener('games-filter-changed', eventListener);

			wrapper = mount(GameFilter, {
				props: {
					platformsData: JSON.stringify(mockPlatforms),
				},
			});

			await wrapper.vm.$nextTick();

			const platformSelect = wrapper.find('#platform-filter');
			await platformSelect.setValue('PlayStation 5');

			expect(eventListener).toHaveBeenCalled();
			expect(eventListener.mock.calls[0][0].detail).toEqual({
				search: '',
				platform: 'PlayStation 5',
				sort: 'title-asc',
			});

			window.removeEventListener('games-filter-changed', eventListener);
		});

		it('updates sort filter and dispatches event', async () => {
			const eventListener = jest.fn();
			window.addEventListener('games-filter-changed', eventListener);

			wrapper = mount(GameFilter, {
				props: {
					platformsData: JSON.stringify(mockPlatforms),
				},
			});

			const sortSelect = wrapper.find('#sort-filter');
			await sortSelect.setValue('date-desc');

			expect(eventListener).toHaveBeenCalled();
			expect(eventListener.mock.calls[0][0].detail).toEqual({
				search: '',
				platform: '',
				sort: 'date-desc',
			});

			window.removeEventListener('games-filter-changed', eventListener);
		});

		it('updates URL parameters when filters change', async () => {
			wrapper = mount(GameFilter, {
				props: {
					platformsData: JSON.stringify(mockPlatforms),
				},
			});

			const searchInput = wrapper.find('#search-filter');
			await searchInput.setValue('mario');

			const platformSelect = wrapper.find('#platform-filter');
			await platformSelect.setValue('Nintendo Switch');

			const sortSelect = wrapper.find('#sort-filter');
			await sortSelect.setValue('date-desc');

			const urlParams = new URLSearchParams(window.location.search);
			expect(urlParams.get('search')).toBe('mario');
			expect(urlParams.get('platform')).toBe('Nintendo Switch');
			expect(urlParams.get('sort')).toBe('date-desc');
		});

		it('loads filters from URL parameters on mount', async () => {
			// Set URL parameters
			window.history.replaceState(
				{},
				'',
				'?search=sonic&platform=PC&sort=title-desc',
			);

			wrapper = mount(GameFilter, {
				props: {
					platformsData: JSON.stringify(mockPlatforms),
				},
			});

			await wrapper.vm.$nextTick();

			expect(wrapper.vm.searchQuery).toBe('sonic');
			expect(wrapper.vm.selectedPlatform).toBe('PC');
			expect(wrapper.vm.selectedSort).toBe('title-desc');
		});
	});

	describe('Clear Filters', () => {
		it('shows clear button when filters are active', async () => {
			wrapper = mount(GameFilter, {
				props: {
					platformsData: JSON.stringify(mockPlatforms),
				},
			});

			// Initially no clear button
			expect(wrapper.find('.clear-filters-btn').exists()).toBe(false);

			// Add a filter
			const searchInput = wrapper.find('#search-filter');
			await searchInput.setValue('test');
			await wrapper.vm.$nextTick();

			// Clear button should appear
			expect(wrapper.find('.clear-filters-btn').exists()).toBe(true);
		});

		it('clears all filters when clear button is clicked', async () => {
			wrapper = mount(GameFilter, {
				props: {
					platformsData: JSON.stringify(mockPlatforms),
				},
			});

			// Set filters
			await wrapper.find('#search-filter').setValue('test');
			await wrapper.find('#platform-filter').setValue('PC');
			await wrapper.find('#sort-filter').setValue('date-desc');
			await wrapper.vm.$nextTick();

			// Click clear button
			await wrapper.find('.clear-filters-btn').trigger('click');
			await wrapper.vm.$nextTick();

			// All filters should be reset
			expect(wrapper.vm.searchQuery).toBe('');
			expect(wrapper.vm.selectedPlatform).toBe('');
			expect(wrapper.vm.selectedSort).toBe('title-asc');

			// URL should be cleared
			expect(window.location.search).toBe('');
		});
	});

	describe('Edge Cases', () => {
		it('handles empty platforms array', () => {
			wrapper = mount(GameFilter, {
				props: {
					platformsData: JSON.stringify([]),
				},
			});

			const options = wrapper.find('#platform-filter').findAll('option');
			expect(options).toHaveLength(1); // Only "All Platforms"
		});

		it('handles invalid JSON in platformsData', () => {
			wrapper = mount(GameFilter, {
				props: {
					platformsData: 'invalid-json',
				},
			});

			// Should render without crashing
			expect(wrapper.find('.game-filter').exists()).toBe(true);
			const options = wrapper.find('#platform-filter').findAll('option');
			expect(options).toHaveLength(1); // Only "All Platforms"
		});

		it('handles missing platformsData prop', async () => {
			// Suppress console.error and console.warn for this test
			const consoleSpy = jest
				.spyOn(console, 'error')
				.mockImplementation(() => {});
			const warnSpy = jest.spyOn(console, 'warn').mockImplementation(() => {});

			wrapper = mount(GameFilter, {
				props: {
					platformsData: '',
				},
			});

			await wrapper.vm.$nextTick();

			// Should render without crashing
			expect(wrapper.find('.game-filter').exists()).toBe(true);

			consoleSpy.mockRestore();
			warnSpy.mockRestore();
		});

		it('dispatches event on every search input change', async () => {
			const eventListener = jest.fn();
			window.addEventListener('games-filter-changed', eventListener);

			wrapper = mount(GameFilter, {
				props: {
					platformsData: JSON.stringify(mockPlatforms),
				},
			});

			const searchInput = wrapper.find('#search-filter');
			await searchInput.setValue('a');
			await searchInput.setValue('ab');
			await searchInput.setValue('abc');

			expect(eventListener).toHaveBeenCalledTimes(3);

			window.removeEventListener('games-filter-changed', eventListener);
		});
	});
});
