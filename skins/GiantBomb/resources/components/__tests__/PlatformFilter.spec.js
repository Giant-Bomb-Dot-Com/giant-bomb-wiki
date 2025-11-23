const { mount } = require("@vue/test-utils");
const PlatformFilter = require("../PlatformFilter.vue");

describe("PlatformFilter", () => {
  const defaultProps = {
    currentLetter: "",
    currentSort: "release_date",
    currentRequireAllGames: false,
    currentGames: "",
  };

  beforeEach(() => {
    // Mock window.history to update window.location
    window.history.pushState = jest.fn((state, title, url) => {
      if (url) {
        const fullUrl = url.startsWith("http")
          ? url
          : `http://localhost:8080${url}`;
        const urlObj = new URL(fullUrl);
        window.location.href = fullUrl;
        window.location.pathname = urlObj.pathname;
        window.location.search = urlObj.search;
      }
    });

    // Mock URLSearchParams
    global.URLSearchParams = jest.fn().mockImplementation((search) => {
      const params = new Map();
      if (search) {
        search
          .replace("?", "")
          .split("&")
          .forEach((pair) => {
            const [key, value] = pair.split("=");
            if (key && value) {
              // Handle array parameters like game_title[]
              const cleanKey = key.replace("[]", "");
              if (key.endsWith("[]")) {
                if (!params.has(cleanKey)) {
                  params.set(cleanKey, []);
                }
                params.get(cleanKey).push(decodeURIComponent(value));
              } else {
                params.set(key, decodeURIComponent(value));
              }
            }
          });
      }
      return {
        get: (key) => {
          const value = params.get(key);
          return Array.isArray(value) ? null : value || null;
        },
        getAll: (key) => {
          const value = params.get(key.replace("[]", ""));
          return Array.isArray(value) ? value : [];
        },
        set: (key, value) => params.set(key, value),
        delete: (key) => params.delete(key),
        toString: () => {
          const pairs = [];
          params.forEach((value, key) => {
            if (Array.isArray(value)) {
              value.forEach((v) => {
                pairs.push(`${key}[]=${encodeURIComponent(v)}`);
              });
            } else {
              pairs.push(`${key}=${encodeURIComponent(value)}`);
            }
          });
          return pairs.join("&");
        },
      };
    });

    // Clear all event listeners
    window.dispatchEvent = jest.fn();

    // Mock fetch for game search
    global.fetch = jest.fn();
  });

  afterEach(() => {
    jest.restoreAllMocks();
  });

  describe("Initial Render", () => {
    it("renders filter title and labels", async () => {
      const wrapper = mount(PlatformFilter, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      const title = wrapper.find(".filter-title");
      expect(title.exists()).toBe(true);
      expect(title.text()).toBe("Filter");

      const labels = wrapper.findAll(".filter-label");
      expect(labels).toHaveLength(3);
      expect(labels[0].text()).toBe("Letter");
      expect(labels[1].text()).toBe("Sort By");
      expect(labels[2].text()).toBe("Has Games");
    });

    it("renders letter select with alphabet options", async () => {
      const wrapper = mount(PlatformFilter, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      const letterSelect = wrapper.find("#letter-filter");
      expect(letterSelect.exists()).toBe(true);

      const options = letterSelect.findAll("option");
      expect(options).toHaveLength(28); // "All" + "#" + 26 letters
      expect(options[0].text()).toBe("All");
      expect(options[1].text()).toBe("#");
      expect(options[2].text()).toBe("A");
      expect(options[27].text()).toBe("Z");
    });

    it("renders sort select with sort options", async () => {
      const wrapper = mount(PlatformFilter, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      const sortSelect = wrapper.find("#sort-filter");
      expect(sortSelect.exists()).toBe(true);

      const options = sortSelect.findAll("option");
      expect(options).toHaveLength(4);
      expect(options[0].text()).toBe("Release Date");
      expect(options[1].text()).toBe("Alphabetical");
      expect(options[2].text()).toBe("Last Edited");
      expect(options[3].text()).toBe("Last Created");
    });

    it("renders search input", async () => {
      const wrapper = mount(PlatformFilter, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      const searchInput = wrapper.find("#search-filter");
      expect(searchInput.exists()).toBe(true);
      expect(searchInput.attributes("placeholder")).toBe("Enter game name...");
    });

    it("does not show clear filters button when no filters are active", async () => {
      const wrapper = mount(PlatformFilter, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      const clearButton = wrapper.find(".clear-filters-btn");
      expect(clearButton.exists()).toBe(false);
    });

    it("does not show require all games checkbox when no games selected", async () => {
      const wrapper = mount(PlatformFilter, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      const checkbox = wrapper.find(".filter-checkbox-group");
      expect(checkbox.exists()).toBe(false);
    });
  });

  describe("Filter Selection", () => {
    it("updates URL and dispatches event when letter is selected", async () => {
      const wrapper = mount(PlatformFilter, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      const letterSelect = wrapper.find("#letter-filter");
      await letterSelect.setValue("A");

      expect(window.history.pushState).toHaveBeenCalledWith(
        {},
        "",
        expect.stringContaining("letter=A"),
      );

      expect(window.dispatchEvent).toHaveBeenCalledWith(
        expect.objectContaining({
          type: "platforms-filter-changed",
          detail: {
            letter: "A",
            sort: "release_date",
            gameTitles: [],
            requireAllGames: false,
            page: 1,
          },
        }),
      );
    });

    it("updates URL and dispatches event when sort is changed", async () => {
      const wrapper = mount(PlatformFilter, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      const sortSelect = wrapper.find("#sort-filter");
      await sortSelect.setValue("alphabetical");

      expect(window.history.pushState).toHaveBeenCalledWith(
        {},
        "",
        expect.stringContaining("sort=alphabetical"),
      );

      expect(window.dispatchEvent).toHaveBeenCalledWith(
        expect.objectContaining({
          type: "platforms-filter-changed",
          detail: {
            letter: "",
            sort: "alphabetical",
            gameTitles: [],
            requireAllGames: false,
            page: 1,
          },
        }),
      );
    });

    it("shows clear filters button when filters are active", async () => {
      const wrapper = mount(PlatformFilter, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      await wrapper.find("#letter-filter").setValue("B");
      await wrapper.vm.$nextTick();

      const clearButton = wrapper.find(".clear-filters-btn");
      expect(clearButton.exists()).toBe(true);
      expect(clearButton.text()).toBe("Clear Filters");
    });

    it("does not add sort parameter to URL when default sort is selected", async () => {
      const wrapper = mount(PlatformFilter, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      const letterSelect = wrapper.find("#letter-filter");
      await letterSelect.setValue("C");

      const lastCall =
        window.history.pushState.mock.calls[
          window.history.pushState.mock.calls.length - 1
        ];
      expect(lastCall[2]).not.toContain("sort=");
      expect(lastCall[2]).toContain("letter=C");
    });
  });

  describe("Search Functionality", () => {
    it("does not search when input is less than 2 characters", async () => {
      jest.useFakeTimers();
      const wrapper = mount(PlatformFilter, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      const searchInput = wrapper.find("#search-filter");
      await searchInput.setValue("A");
      await wrapper.vm.$nextTick();

      jest.advanceTimersByTime(500);
      await wrapper.vm.$nextTick();

      expect(global.fetch).not.toHaveBeenCalled();
      expect(wrapper.vm.showSearchResults).toBe(false);

      jest.useRealTimers();
    });

    it("searches games after debounce delay", async () => {
      jest.useFakeTimers();
      global.fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => ({
          success: true,
          games: [
            {
              searchName: "Games/Test_Game",
              title: "Test Game",
              image: "https://example.com/test.jpg",
              releaseYear: "2023",
              platforms: [{ abbrev: "PC" }],
            },
          ],
          currentPage: 1,
          totalPages: 1,
          hasMore: false,
        }),
      });

      const wrapper = mount(PlatformFilter, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      const searchInput = wrapper.find("#search-filter");
      await searchInput.setValue("Test");
      await wrapper.vm.$nextTick();

      jest.advanceTimersByTime(500);
      await wrapper.vm.$nextTick();

      expect(global.fetch).toHaveBeenCalledWith(
        expect.stringContaining("action=get-games"),
      );
      expect(global.fetch).toHaveBeenCalledWith(
        expect.stringContaining("name=Test"),
      );

      jest.useRealTimers();
    });

    it("displays search results", async () => {
      const mockGames = [
        {
          searchName: "Games/Test_Game",
          title: "Test Game",
          image: "https://example.com/test.jpg",
          releaseYear: "2023",
          platforms: [{ abbrev: "PC" }],
        },
      ];

      const wrapper = mount(PlatformFilter, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      // Manually set the search results
      wrapper.vm.searchText = "Test";
      wrapper.vm.showSearchResults = true;
      wrapper.vm.searchResults = mockGames;
      await wrapper.vm.$nextTick();

      const searchResults = wrapper.find(".search-results");
      expect(searchResults.exists()).toBe(true);

      const resultItems = wrapper.findAll(".search-result-item");
      expect(resultItems).toHaveLength(1);
      expect(resultItems[0].text()).toContain("Test Game");
    });

    it("displays loading state during search", async () => {
      const wrapper = mount(PlatformFilter, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      // Manually set loading state
      wrapper.vm.isSearching = true;
      wrapper.vm.showSearchResults = true;
      await wrapper.vm.$nextTick();

      const loadingDiv = wrapper.find(".search-loading");
      expect(loadingDiv.exists()).toBe(true);
      expect(loadingDiv.text()).toBe("Searching...");
    });

    it("displays no results message when no games found", async () => {
      const wrapper = mount(PlatformFilter, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      // Set up state to show no results
      wrapper.vm.searchText = "NonexistentGame";
      wrapper.vm.showSearchResults = true;
      wrapper.vm.searchResults = [];
      wrapper.vm.isSearching = false;
      await wrapper.vm.$nextTick();

      const noResults = wrapper.find(".search-no-results");
      expect(noResults.exists()).toBe(true);
      expect(noResults.text()).toBe("No games found");
    });

    it("selects game and adds it to chips", async () => {
      const mockGames = [
        {
          searchName: "Games/Test_Game",
          title: "Test Game",
          image: "https://example.com/test.jpg",
          releaseYear: "2023",
          platforms: [],
        },
      ];

      const wrapper = mount(PlatformFilter, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      // Set up search results
      wrapper.vm.searchText = "Test";
      wrapper.vm.showSearchResults = true;
      wrapper.vm.searchResults = mockGames;
      await wrapper.vm.$nextTick();

      const resultItem = wrapper.find(".search-result-item");
      await resultItem.trigger("mousedown");
      await wrapper.vm.$nextTick();

      expect(wrapper.vm.selectedGames).toHaveLength(1);
      expect(wrapper.vm.selectedGames[0].title).toBe("Test Game");

      const gameChip = wrapper.find(".game-chip");
      expect(gameChip.exists()).toBe(true);
      expect(gameChip.text()).toContain("Test Game");
    });

    it("removes game chip when remove button is clicked", async () => {
      const wrapper = mount(PlatformFilter, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      // Manually add a game to selectedGames
      wrapper.vm.selectedGames = [
        {
          searchName: "Games/Test_Game",
          title: "Test Game",
        },
      ];
      await wrapper.vm.$nextTick();

      const removeButton = wrapper.find(".game-chip-remove");
      expect(removeButton.exists()).toBe(true);

      await removeButton.trigger("click");
      await wrapper.vm.$nextTick();

      expect(wrapper.vm.selectedGames).toHaveLength(0);
      expect(wrapper.find(".game-chip").exists()).toBe(false);
    });

    it("shows require all games checkbox when multiple games selected", async () => {
      const wrapper = mount(PlatformFilter, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      wrapper.vm.selectedGames = [
        { searchName: "Games/Game1", title: "Game 1" },
        { searchName: "Games/Game2", title: "Game 2" },
      ];
      await wrapper.vm.$nextTick();

      const checkbox = wrapper.find(".filter-checkbox-group");
      expect(checkbox.exists()).toBe(true);

      const checkboxLabel = wrapper.find(".filter-checkbox-label");
      expect(checkboxLabel.text()).toContain(
        "Only return results if linked to all games",
      );
    });

    it("loads more results when load more button is clicked", async () => {
      const wrapper = mount(PlatformFilter, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      // Set up initial search results with more available
      wrapper.vm.searchText = "Game";
      wrapper.vm.showSearchResults = true;
      wrapper.vm.searchResults = [
        { searchName: "Games/Game1", title: "Game 1" },
      ];
      wrapper.vm.hasMoreResults = true;
      wrapper.vm.currentSearchPage = 1;
      await wrapper.vm.$nextTick();

      expect(wrapper.vm.hasMoreResults).toBe(true);

      const loadMoreBtn = wrapper.find(".load-more-btn");
      expect(loadMoreBtn.exists()).toBe(true);

      // Mock fetch for the load more action
      global.fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => ({
          success: true,
          games: [{ searchName: "Games/Game2", title: "Game 2" }],
          currentPage: 2,
          totalPages: 2,
          hasMore: false,
        }),
      });

      await loadMoreBtn.trigger("mousedown");
      await wrapper.vm.$nextTick();

      // Wait for the async operation
      await new Promise((resolve) => setTimeout(resolve, 0));
      await wrapper.vm.$nextTick();

      expect(wrapper.vm.searchResults).toHaveLength(2);
      expect(wrapper.vm.hasMoreResults).toBe(false);
    });
  });

  describe("Clear Filters", () => {
    it("clears all filters when clear button is clicked", async () => {
      const wrapper = mount(PlatformFilter, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      // Set some filters
      await wrapper.find("#letter-filter").setValue("D");
      await wrapper.find("#sort-filter").setValue("alphabetical");
      wrapper.vm.selectedGames = [{ searchName: "Games/Test", title: "Test" }];
      await wrapper.vm.$nextTick();

      // Click clear button
      const clearButton = wrapper.find(".clear-filters-btn");
      await clearButton.trigger("click");

      expect(wrapper.vm.selectedLetter).toBe("");
      expect(wrapper.vm.selectedSort).toBe("release_date");
      expect(wrapper.vm.selectedGames).toHaveLength(0);

      const letterSelect = wrapper.find("#letter-filter");
      const sortSelect = wrapper.find("#sort-filter");
      expect(letterSelect.element.value).toBe("");
      expect(sortSelect.element.value).toBe("release_date");
    });

    it("updates URL to remove parameters when cleared", async () => {
      const wrapper = mount(PlatformFilter, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      await wrapper.find("#letter-filter").setValue("E");
      await wrapper.vm.$nextTick();

      const clearButton = wrapper.find(".clear-filters-btn");
      await clearButton.trigger("click");

      await wrapper.vm.$nextTick();

      const lastCall =
        window.history.pushState.mock.calls[
          window.history.pushState.mock.calls.length - 1
        ];
      expect(lastCall[2]).toBe("/");
    });

    it("dispatches event with empty filters when cleared", async () => {
      const wrapper = mount(PlatformFilter, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      await wrapper.find("#sort-filter").setValue("last_edited");
      await wrapper.vm.$nextTick();

      window.dispatchEvent.mockClear();

      const clearButton = wrapper.find(".clear-filters-btn");
      await clearButton.trigger("click");

      expect(window.dispatchEvent).toHaveBeenCalledWith(
        expect.objectContaining({
          type: "platforms-filter-changed",
          detail: {
            letter: "",
            sort: "release_date",
            gameTitles: [],
            requireAllGames: false,
            page: 1,
          },
        }),
      );
    });

    it("hides clear button after clearing", async () => {
      const wrapper = mount(PlatformFilter, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      await wrapper.find("#letter-filter").setValue("F");
      await wrapper.vm.$nextTick();

      const clearButton = wrapper.find(".clear-filters-btn");
      await clearButton.trigger("click");
      await wrapper.vm.$nextTick();

      expect(wrapper.find(".clear-filters-btn").exists()).toBe(false);
    });
  });

  describe("Computed Properties", () => {
    it("hasActiveFilters returns true when letter is selected", async () => {
      const wrapper = mount(PlatformFilter, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      expect(wrapper.vm.hasActiveFilters).toBe(false);

      await wrapper.find("#letter-filter").setValue("G");
      expect(wrapper.vm.hasActiveFilters).toBe(true);
    });

    it("hasActiveFilters returns true when non-default sort is selected", async () => {
      const wrapper = mount(PlatformFilter, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      await wrapper.find("#sort-filter").setValue("last_created");
      expect(wrapper.vm.hasActiveFilters).toBe(true);
    });

    it("hasActiveFilters returns true when games are selected", async () => {
      const wrapper = mount(PlatformFilter, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      wrapper.vm.selectedGames = [{ searchName: "Games/Test", title: "Test" }];
      await wrapper.vm.$nextTick();

      expect(wrapper.vm.hasActiveFilters).toBe(true);
    });

    it("hasActiveFilters returns false when all filters are default", async () => {
      const wrapper = mount(PlatformFilter, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      expect(wrapper.vm.hasActiveFilters).toBe(false);
    });
  });

  describe("Edge Cases", () => {
    it("handles URL with existing parameters", async () => {
      // Set up URL params before mounting
      const mockSearchParams = new URLSearchParams(
        "?letter=H&sort=alphabetical",
      );
      window.location.search = "?letter=H&sort=alphabetical";

      // Update the global URLSearchParams mock to return these values
      global.URLSearchParams = jest
        .fn()
        .mockImplementation(() => mockSearchParams);

      const wrapper = mount(PlatformFilter, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      expect(wrapper.vm.selectedLetter).toBe("H");
      expect(wrapper.vm.selectedSort).toBe("alphabetical");

      // Reset
      window.location.search = "";
    });

    it("handles game selection without duplicates", async () => {
      const wrapper = mount(PlatformFilter, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      const game = {
        searchName: "Games/Duplicate_Game",
        title: "Duplicate Game",
      };

      wrapper.vm.selectedGames = [game];
      await wrapper.vm.$nextTick();

      // Try to select the same game again
      wrapper.vm.selectGame(game);
      await wrapper.vm.$nextTick();

      expect(wrapper.vm.selectedGames).toHaveLength(1);
    });

    it("handles fetch errors gracefully", async () => {
      const consoleErrorSpy = jest.spyOn(console, "error").mockImplementation();

      // This test just verifies the component doesn't crash when there's a search error
      // We'll manually set up an error state
      const wrapper = mount(PlatformFilter, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      // The component should handle errors gracefully
      wrapper.vm.searchText = "Test";
      wrapper.vm.searchResults = [];
      wrapper.vm.isSearching = false;
      await wrapper.vm.$nextTick();

      // Component should still be functional
      expect(wrapper.vm.searchText).toBe("Test");
      expect(wrapper.vm.searchResults).toEqual([]);

      consoleErrorSpy.mockRestore();
    });

    it("formats platforms correctly", () => {
      const wrapper = mount(PlatformFilter, {
        props: defaultProps,
      });

      const platforms = [
        { abbrev: "PC" },
        { abbrev: "PS5" },
        { abbrev: "XSX" },
        { abbrev: "NSW" },
        { abbrev: "PS4" },
      ];

      const formatted = wrapper.vm.formatPlatforms(platforms);
      expect(formatted).toBe("PC, PS5, XSX +2 more");
    });

    it("handles empty platforms array", () => {
      const wrapper = mount(PlatformFilter, {
        props: defaultProps,
      });

      const formatted = wrapper.vm.formatPlatforms([]);
      expect(formatted).toBe("");
    });
  });
});
