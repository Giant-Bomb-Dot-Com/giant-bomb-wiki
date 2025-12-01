const { mount } = require("@vue/test-utils");
const SearchableMultiSelect = require("../SearchableMultiSelect.vue");

describe("SearchableMultiSelect", () => {
  let wrapper;

  const defaultProps = {
    id: "test-select",
    label: "Test Select",
    placeholder: "Search items...",
    selectedItems: [],
    searchResults: [],
    isSearching: false,
    hasMoreResults: false,
    isLoadingMore: false,
    itemKey: "id",
    itemLabel: "name",
  };

  beforeEach(() => {
    jest.clearAllMocks();
    jest.useFakeTimers();
  });

  afterEach(() => {
    if (wrapper) {
      wrapper.unmount();
    }
    jest.useRealTimers();
  });

  describe("Component Rendering", () => {
    it("renders with default props", () => {
      wrapper = mount(SearchableMultiSelect, {
        props: defaultProps,
      });

      expect(wrapper.find(".filter-group.search-group").exists()).toBe(true);
      expect(wrapper.find("label").text()).toBe("Test Select");
      expect(wrapper.find("input").attributes("placeholder")).toBe(
        "Search items...",
      );
    });

    it("renders without label when not provided", () => {
      wrapper = mount(SearchableMultiSelect, {
        props: {
          ...defaultProps,
          label: "",
        },
      });

      expect(wrapper.find("label").exists()).toBe(false);
    });

    it("renders selected items as chips", () => {
      const selectedItems = [
        { id: "1", name: "Item 1" },
        { id: "2", name: "Item 2" },
      ];

      wrapper = mount(SearchableMultiSelect, {
        props: {
          ...defaultProps,
          selectedItems,
        },
      });

      const chips = wrapper.findAll(".item-chip");
      expect(chips).toHaveLength(2);
      expect(chips[0].text()).toContain("Item 1");
      expect(chips[1].text()).toContain("Item 2");
    });

    it("does not render chips when no items selected", () => {
      wrapper = mount(SearchableMultiSelect, {
        props: defaultProps,
      });

      expect(wrapper.find(".selected-items").exists()).toBe(false);
    });
  });

  describe("Search Input", () => {
    it("updates search text on input", async () => {
      wrapper = mount(SearchableMultiSelect, {
        props: defaultProps,
      });

      const input = wrapper.find("input");
      await input.setValue("test");

      expect(wrapper.vm.searchText).toBe("test");
    });

    it("debounces search input", async () => {
      wrapper = mount(SearchableMultiSelect, {
        props: {
          ...defaultProps,
          debounceMs: 400,
        },
      });

      const input = wrapper.find("input");
      await input.setValue("te");

      // Should not emit immediately
      expect(wrapper.emitted("search")).toBeFalsy();

      // Fast forward past debounce time
      jest.advanceTimersByTime(400);

      // Should emit after debounce
      expect(wrapper.emitted("search")).toBeTruthy();
      expect(wrapper.emitted("search")[0]).toEqual(["te"]);
    });

    it("does not search when input is below minSearchLength", async () => {
      wrapper = mount(SearchableMultiSelect, {
        props: {
          ...defaultProps,
          minSearchLength: 2,
        },
      });

      const input = wrapper.find("input");
      await input.setValue("a");

      jest.advanceTimersByTime(400);

      expect(wrapper.emitted("search")).toBeFalsy();
      expect(wrapper.vm.showSearchResults).toBe(false);
    });

    it("clears previous debounce timer on new input", async () => {
      wrapper = mount(SearchableMultiSelect, {
        props: defaultProps,
      });

      const input = wrapper.find("input");
      await input.setValue("te");

      jest.advanceTimersByTime(200);

      await input.setValue("tes");

      jest.advanceTimersByTime(200);

      // Should not have emitted yet (timer was reset)
      expect(wrapper.emitted("search")).toBeFalsy();

      jest.advanceTimersByTime(200);

      // Should emit now with the latest value
      expect(wrapper.emitted("search")).toBeTruthy();
      expect(wrapper.emitted("search")[0]).toEqual(["tes"]);
    });
  });

  describe("Search Results", () => {
    it("shows search results when search text is long enough", async () => {
      wrapper = mount(SearchableMultiSelect, {
        props: {
          ...defaultProps,
          searchResults: [
            { id: "1", name: "Result 1" },
            { id: "2", name: "Result 2" },
          ],
        },
      });

      const input = wrapper.find("input");
      await input.setValue("te");

      jest.advanceTimersByTime(400);
      await wrapper.vm.$nextTick();

      expect(wrapper.vm.showSearchResults).toBe(true);
    });

    it("displays loading state when searching", async () => {
      wrapper = mount(SearchableMultiSelect, {
        props: {
          ...defaultProps,
          isSearching: true,
        },
      });

      // Directly set the internal state
      wrapper.vm.showSearchResults = true;
      await wrapper.vm.$nextTick();

      expect(wrapper.find(".search-loading").exists()).toBe(true);
      expect(wrapper.find(".search-loading").text()).toBe("Searching...");
    });

    it("displays search results when available", async () => {
      wrapper = mount(SearchableMultiSelect, {
        props: {
          ...defaultProps,
          searchResults: [
            { id: "1", name: "Result 1" },
            { id: "2", name: "Result 2" },
          ],
        },
      });

      // Trigger search by typing
      const input = wrapper.find("input");
      await input.setValue("te");
      wrapper.vm.showSearchResults = true;
      await wrapper.vm.$nextTick();

      const results = wrapper.findAll(".search-result-item");
      expect(results).toHaveLength(2);
    });

    it("displays no results message when search returns empty", async () => {
      wrapper = mount(SearchableMultiSelect, {
        props: {
          ...defaultProps,
          searchResults: [],
          noResultsText: "No items found",
        },
      });

      // Set search text and show results
      wrapper.vm.searchText = "test";
      wrapper.vm.showSearchResults = true;
      await wrapper.vm.$nextTick();

      expect(wrapper.find(".search-no-results").exists()).toBe(true);
      expect(wrapper.find(".search-no-results").text()).toBe("No items found");
    });

    it("shows load more button when hasMoreResults is true", async () => {
      wrapper = mount(SearchableMultiSelect, {
        props: {
          ...defaultProps,
          searchResults: [{ id: "1", name: "Result 1" }],
          hasMoreResults: true,
          loadMoreText: "Load more items",
        },
      });

      wrapper.vm.searchText = "te";
      wrapper.vm.showSearchResults = true;
      await wrapper.vm.$nextTick();

      const loadMoreBtn = wrapper.find(".load-more-btn");
      expect(loadMoreBtn.exists()).toBe(true);
      expect(loadMoreBtn.text()).toBe("Load more items");
    });

    it("disables load more button when loading", async () => {
      wrapper = mount(SearchableMultiSelect, {
        props: {
          ...defaultProps,
          searchResults: [{ id: "1", name: "Result 1" }],
          hasMoreResults: true,
          isLoadingMore: true,
        },
      });

      wrapper.vm.searchText = "te";
      wrapper.vm.showSearchResults = true;
      await wrapper.vm.$nextTick();

      const loadMoreBtn = wrapper.find(".load-more-btn");
      expect(loadMoreBtn.attributes("disabled")).toBeDefined();
      expect(loadMoreBtn.text()).toBe("Loading...");
    });
  });

  describe("Item Selection", () => {
    it("selects an item when clicked", async () => {
      const searchResults = [
        { id: "1", name: "Item 1" },
        { id: "2", name: "Item 2" },
      ];

      wrapper = mount(SearchableMultiSelect, {
        props: {
          ...defaultProps,
          searchResults,
        },
      });

      wrapper.vm.showSearchResults = true;
      wrapper.vm.searchText = "te";
      await wrapper.vm.$nextTick();

      const firstResult = wrapper.findAll(".search-result-item")[0];
      await firstResult.trigger("mousedown");

      expect(wrapper.emitted("update:selectedItems")).toBeTruthy();
      expect(wrapper.emitted("update:selectedItems")[0][0]).toEqual([
        searchResults[0],
      ]);
      expect(wrapper.emitted("select")).toBeTruthy();
      expect(wrapper.emitted("select")[0][0]).toEqual(searchResults[0]);
    });

    it("prevents duplicate selection", async () => {
      const item = { id: "1", name: "Item 1" };
      const searchResults = [item, { id: "2", name: "Item 2" }];

      wrapper = mount(SearchableMultiSelect, {
        props: {
          ...defaultProps,
          selectedItems: [item],
          searchResults,
        },
      });

      wrapper.vm.showSearchResults = true;
      wrapper.vm.searchText = "te";
      await wrapper.vm.$nextTick();

      // Try to select the already-selected item
      const firstResult = wrapper.findAll(".search-result-item")[0];
      await firstResult.trigger("mousedown");

      // Should not emit update:selectedItems since item is already selected
      expect(wrapper.emitted("update:selectedItems")).toBeFalsy();
      expect(wrapper.emitted("select")).toBeFalsy();
    });

    it("clears search text after selection", async () => {
      const searchResults = [{ id: "1", name: "Item 1" }];

      wrapper = mount(SearchableMultiSelect, {
        props: {
          ...defaultProps,
          searchResults,
        },
      });

      wrapper.vm.showSearchResults = true;
      wrapper.vm.searchText = "test";
      await wrapper.vm.$nextTick();

      const firstResult = wrapper.find(".search-result-item");
      await firstResult.trigger("mousedown");

      expect(wrapper.vm.searchText).toBe("");
      expect(wrapper.vm.showSearchResults).toBe(false);
    });

    it("handles selection with string items", async () => {
      const searchResults = ["Item 1", "Item 2"];

      wrapper = mount(SearchableMultiSelect, {
        props: {
          ...defaultProps,
          searchResults,
          itemKey: null,
          itemLabel: null,
        },
      });

      wrapper.vm.showSearchResults = true;
      wrapper.vm.searchText = "te";
      await wrapper.vm.$nextTick();

      const firstResult = wrapper.findAll(".search-result-item")[0];
      await firstResult.trigger("mousedown");

      expect(wrapper.emitted("update:selectedItems")[0][0]).toEqual(["Item 1"]);
    });
  });

  describe("Item Removal", () => {
    it("removes an item when remove button is clicked", async () => {
      const selectedItems = [
        { id: "1", name: "Item 1" },
        { id: "2", name: "Item 2" },
      ];

      wrapper = mount(SearchableMultiSelect, {
        props: {
          ...defaultProps,
          selectedItems,
        },
      });

      const removeButtons = wrapper.findAll(".item-chip-remove");
      await removeButtons[0].trigger("click");

      expect(wrapper.emitted("update:selectedItems")).toBeTruthy();
      expect(wrapper.emitted("update:selectedItems")[0][0]).toEqual([
        selectedItems[1],
      ]);
      expect(wrapper.emitted("remove")).toBeTruthy();
      expect(wrapper.emitted("remove")[0][0]).toEqual(selectedItems[0]);
    });

    it("displays correct remove button title", () => {
      const selectedItems = [{ id: "1", name: "Test Item" }];

      wrapper = mount(SearchableMultiSelect, {
        props: {
          ...defaultProps,
          selectedItems,
        },
      });

      const removeButton = wrapper.find(".item-chip-remove");
      expect(removeButton.attributes("title")).toBe("Remove Test Item");
    });
  });

  describe("Load More Functionality", () => {
    it("emits loadMore event when load more button is clicked", async () => {
      wrapper = mount(SearchableMultiSelect, {
        props: {
          ...defaultProps,
          searchResults: [{ id: "1", name: "Item 1" }],
          hasMoreResults: true,
        },
      });

      wrapper.vm.showSearchResults = true;
      wrapper.vm.searchText = "te";
      await wrapper.vm.$nextTick();

      const loadMoreBtn = wrapper.find(".load-more-btn");
      await loadMoreBtn.trigger("mousedown");

      expect(wrapper.emitted("loadMore")).toBeTruthy();
    });
  });

  describe("Match All Checkbox", () => {
    it("shows match all checkbox when multiple items selected and showMatchAll is true", () => {
      wrapper = mount(SearchableMultiSelect, {
        props: {
          ...defaultProps,
          selectedItems: [
            { id: "1", name: "Item 1" },
            { id: "2", name: "Item 2" },
          ],
          showMatchAll: true,
          matchAllText: "Match all items",
        },
      });

      const checkbox = wrapper.find(".filter-checkbox-group");
      expect(checkbox.exists()).toBe(true);
      expect(checkbox.text()).toContain("Match all items");
    });

    it("does not show match all checkbox when only one item selected", () => {
      wrapper = mount(SearchableMultiSelect, {
        props: {
          ...defaultProps,
          selectedItems: [{ id: "1", name: "Item 1" }],
          showMatchAll: true,
        },
      });

      expect(wrapper.find(".filter-checkbox-group").exists()).toBe(false);
    });

    it("does not show match all checkbox when showMatchAll is false", () => {
      wrapper = mount(SearchableMultiSelect, {
        props: {
          ...defaultProps,
          selectedItems: [
            { id: "1", name: "Item 1" },
            { id: "2", name: "Item 2" },
          ],
          showMatchAll: false,
        },
      });

      expect(wrapper.find(".filter-checkbox-group").exists()).toBe(false);
    });

    it("emits update:requireAll when checkbox is toggled", async () => {
      wrapper = mount(SearchableMultiSelect, {
        props: {
          ...defaultProps,
          selectedItems: [
            { id: "1", name: "Item 1" },
            { id: "2", name: "Item 2" },
          ],
          showMatchAll: true,
          requireAll: false,
        },
      });

      const checkbox = wrapper.find(".filter-checkbox");
      await checkbox.setValue(true);

      expect(wrapper.emitted("update:requireAll")).toBeTruthy();
      expect(wrapper.emitted("update:requireAll")[0][0]).toBe(true);
    });

    it("reflects requireAll prop value in checkbox", () => {
      wrapper = mount(SearchableMultiSelect, {
        props: {
          ...defaultProps,
          selectedItems: [
            { id: "1", name: "Item 1" },
            { id: "2", name: "Item 2" },
          ],
          showMatchAll: true,
          requireAll: true,
        },
      });

      const checkbox = wrapper.find(".filter-checkbox");
      expect(checkbox.element.checked).toBe(true);
    });
  });

  describe("Focus and Blur", () => {
    it("shows results on focus when search text meets minimum length", async () => {
      wrapper = mount(SearchableMultiSelect, {
        props: {
          ...defaultProps,
          searchResults: [{ id: "1", name: "Item 1" }],
        },
      });

      wrapper.vm.searchText = "test";
      await wrapper.vm.$nextTick();

      const input = wrapper.find("input");
      await input.trigger("focus");

      expect(wrapper.vm.showSearchResults).toBe(true);
    });

    it("hides results on blur after delay", async () => {
      wrapper = mount(SearchableMultiSelect, {
        props: defaultProps,
      });

      wrapper.vm.showSearchResults = true;
      await wrapper.vm.$nextTick();

      const input = wrapper.find("input");
      await input.trigger("blur");

      // Should still be visible immediately
      expect(wrapper.vm.showSearchResults).toBe(true);

      // Advance past blur delay
      jest.advanceTimersByTime(200);
      await wrapper.vm.$nextTick();

      expect(wrapper.vm.showSearchResults).toBe(false);
    });
  });

  describe("Custom Slots", () => {
    it("uses default slot rendering when no slot provided", async () => {
      wrapper = mount(SearchableMultiSelect, {
        props: {
          ...defaultProps,
          searchResults: [{ id: "1", name: "Item 1" }],
        },
      });

      wrapper.vm.showSearchResults = true;
      wrapper.vm.searchText = "te";
      await wrapper.vm.$nextTick();

      const result = wrapper.find(".search-result-item");
      expect(result.text()).toBe("Item 1");
    });

    it("accepts custom result-item slot", async () => {
      wrapper = mount(SearchableMultiSelect, {
        props: {
          ...defaultProps,
          searchResults: [{ id: "1", name: "Item 1" }],
        },
        slots: {
          "result-item": `
            <template #result-item="{ results, selectItem }">
              <div 
                v-for="item in results" 
                :key="item.id"
                @mousedown="selectItem(item)"
                class="custom-result"
              >
                Custom: {{ item.name }}
              </div>
            </template>
          `,
        },
      });

      wrapper.vm.showSearchResults = true;
      wrapper.vm.searchText = "te";
      await wrapper.vm.$nextTick();

      const result = wrapper.find(".custom-result");
      expect(result.exists()).toBe(true);
      expect(result.text()).toContain("Custom: Item 1");
    });
  });

  describe("Helper Functions", () => {
    it("getItemKey returns correct key for objects", () => {
      wrapper = mount(SearchableMultiSelect, {
        props: {
          ...defaultProps,
          itemKey: "id",
        },
      });

      const item = { id: "123", name: "Test" };
      expect(wrapper.vm.getItemKey(item)).toBe("123");
    });

    it("getItemKey returns item itself for non-objects", () => {
      wrapper = mount(SearchableMultiSelect, {
        props: {
          ...defaultProps,
          itemKey: null,
        },
      });

      expect(wrapper.vm.getItemKey("test")).toBe("test");
    });

    it("getItemLabel returns correct label for objects", () => {
      wrapper = mount(SearchableMultiSelect, {
        props: {
          ...defaultProps,
          itemLabel: "name",
        },
      });

      const item = { id: "123", name: "Test Item" };
      expect(wrapper.vm.getItemLabel(item)).toBe("Test Item");
    });

    it("getItemLabel returns item itself for non-objects", () => {
      wrapper = mount(SearchableMultiSelect, {
        props: {
          ...defaultProps,
          itemLabel: null,
        },
      });

      expect(wrapper.vm.getItemLabel("test")).toBe("test");
    });
  });

  describe("Edge Cases", () => {
    it("handles empty search results array", async () => {
      wrapper = mount(SearchableMultiSelect, {
        props: {
          ...defaultProps,
          searchResults: [],
        },
      });

      wrapper.vm.showSearchResults = true;
      wrapper.vm.searchText = "test";
      await wrapper.vm.$nextTick();

      expect(wrapper.find(".search-no-results").exists()).toBe(true);
    });

    it("handles empty selected items array", () => {
      wrapper = mount(SearchableMultiSelect, {
        props: {
          ...defaultProps,
          selectedItems: [],
        },
      });

      expect(wrapper.find(".selected-items").exists()).toBe(false);
    });

    it("handles selection of item with same key but different object", async () => {
      const item1 = { id: "1", name: "Item 1" };
      const item1Duplicate = { id: "1", name: "Item 1 Updated" };

      wrapper = mount(SearchableMultiSelect, {
        props: {
          ...defaultProps,
          selectedItems: [item1],
          searchResults: [item1Duplicate],
        },
      });

      wrapper.vm.showSearchResults = true;
      wrapper.vm.searchText = "te";
      await wrapper.vm.$nextTick();

      const result = wrapper.find(".search-result-item");
      await result.trigger("mousedown");

      // Should not add duplicate (same key)
      expect(wrapper.emitted("update:selectedItems")).toBeFalsy();
    });
  });
});
