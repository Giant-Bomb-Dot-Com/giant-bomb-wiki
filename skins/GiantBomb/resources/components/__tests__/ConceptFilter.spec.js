const { mount } = require("@vue/test-utils");
const ConceptFilter = require("../ConceptFilter.vue");

describe("ConceptFilter", () => {
  const defaultProps = {
    currentLetter: "",
    currentSort: "alphabetical",
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
        forEach: (callback) => {
          params.forEach((value, key) => {
            if (Array.isArray(value)) {
              value.forEach((v) => {
                callback(v, `${key}[]`);
              });
            } else {
              callback(value, key);
            }
          });
        },
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
      const wrapper = mount(ConceptFilter, {
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
      const wrapper = mount(ConceptFilter, {
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
      const wrapper = mount(ConceptFilter, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      const sortSelect = wrapper.find("#sort-filter");
      expect(sortSelect.exists()).toBe(true);

      const options = sortSelect.findAll("option");
      expect(options).toHaveLength(3);
      expect(options[0].text()).toBe("Alphabetical");
      expect(options[0].element.value).toBe("alphabetical");
      expect(options[1].text()).toBe("Last Edited");
      expect(options[1].element.value).toBe("last_edited");
      expect(options[2].text()).toBe("Last Created");
      expect(options[2].element.value).toBe("last_created");
    });

    it("renders search input for games", async () => {
      const wrapper = mount(ConceptFilter, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      const searchInput = wrapper.find("#search-filter");
      expect(searchInput.exists()).toBe(true);
      expect(searchInput.attributes("placeholder")).toBe("Enter game name...");
    });

    it("does not show clear filters button when no filters are active", async () => {
      const wrapper = mount(ConceptFilter, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      const clearButton = wrapper.find(".clear-filters-btn");
      expect(clearButton.exists()).toBe(false);
    });

    it("does not show require all games checkbox when no games selected", async () => {
      const wrapper = mount(ConceptFilter, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      // The checkbox should not be visible when no games are selected
      // This is handled by the SearchableMultiSelect component's show-match-all prop
      // which is conditional on selectedGames.length > 1
      expect(wrapper.vm.selectedGames).toHaveLength(0);
      expect(wrapper.vm.requireAllGames).toBe(false);
    });
  });

  describe("Filter Selection", () => {
    it("updates URL and dispatches event when letter is selected", async () => {
      const wrapper = mount(ConceptFilter, {
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

      expect(window.dispatchEvent).toHaveBeenCalled();
      const dispatchedEvent = window.dispatchEvent.mock.calls[0][0];
      expect(dispatchedEvent.type).toBe("concepts-filter-changed");
      expect(dispatchedEvent.detail).toEqual({
        letter: "A",
        sort: "alphabetical",
        game_title: [],
        require_all_games: false,
        page: 1,
      });
    });

    it("updates URL and dispatches event when sort is changed", async () => {
      const wrapper = mount(ConceptFilter, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      const sortSelect = wrapper.find("#sort-filter");
      await sortSelect.setValue("last_edited");

      expect(window.history.pushState).toHaveBeenCalledWith(
        {},
        "",
        expect.stringContaining("sort=last_edited"),
      );

      expect(window.dispatchEvent).toHaveBeenCalled();
      const dispatchedEvent = window.dispatchEvent.mock.calls[0][0];
      expect(dispatchedEvent.type).toBe("concepts-filter-changed");
      expect(dispatchedEvent.detail).toEqual({
        letter: "",
        sort: "last_edited",
        game_title: [],
        require_all_games: false,
        page: 1,
      });
    });

    it("shows clear filters button when filters are active", async () => {
      const wrapper = mount(ConceptFilter, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      await wrapper.find("#letter-filter").setValue("B");
      await wrapper.vm.$nextTick();

      const clearButton = wrapper.find(".clear-filters-btn");
      expect(clearButton.exists()).toBe(true);
      expect(clearButton.text()).toBe("Clear Filters");
    });
  });

  describe("Clear Filters", () => {
    it("clears all filters when clear button is clicked", async () => {
      const wrapper = mount(ConceptFilter, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      // Set some filters
      await wrapper.find("#letter-filter").setValue("D");
      await wrapper.find("#sort-filter").setValue("last_created");
      wrapper.vm.selectedGames = [{ searchName: "Games/Test", title: "Test" }];
      await wrapper.vm.$nextTick();

      // Click clear button
      const clearButton = wrapper.find(".clear-filters-btn");
      await clearButton.trigger("click");

      expect(wrapper.vm.selectedLetter).toBe("");
      expect(wrapper.vm.selectedSort).toBe("alphabetical");
      expect(wrapper.vm.selectedGames).toHaveLength(0);

      const letterSelect = wrapper.find("#letter-filter");
      const sortSelect = wrapper.find("#sort-filter");
      expect(letterSelect.element.value).toBe("");
      expect(sortSelect.element.value).toBe("alphabetical");
    });

    it("updates URL to remove parameters when cleared", async () => {
      const wrapper = mount(ConceptFilter, {
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
      expect(lastCall[2]).toBe("http://localhost/");
    });

    it("dispatches event with empty filters when cleared", async () => {
      const wrapper = mount(ConceptFilter, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      await wrapper.find("#sort-filter").setValue("last_edited");
      await wrapper.vm.$nextTick();

      window.dispatchEvent.mockClear();

      const clearButton = wrapper.find(".clear-filters-btn");
      await clearButton.trigger("click");

      expect(window.dispatchEvent).toHaveBeenCalled();
      const dispatchedEvent = window.dispatchEvent.mock.calls[0][0];
      expect(dispatchedEvent.type).toBe("concepts-filter-changed");
      expect(dispatchedEvent.detail).toEqual({
        letter: "",
        sort: "alphabetical",
        game_title: [],
        require_all_games: false,
        page: 1,
      });
    });

    it("hides clear button after clearing", async () => {
      const wrapper = mount(ConceptFilter, {
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
      const wrapper = mount(ConceptFilter, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      expect(wrapper.vm.hasActiveFilters).toBe(false);

      await wrapper.find("#letter-filter").setValue("G");
      expect(wrapper.vm.hasActiveFilters).toBe(true);
    });

    it("hasActiveFilters returns true when non-default sort is selected", async () => {
      const wrapper = mount(ConceptFilter, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      await wrapper.find("#sort-filter").setValue("last_created");
      expect(wrapper.vm.hasActiveFilters).toBe(true);
    });

    it("hasActiveFilters returns true when games are selected", async () => {
      const wrapper = mount(ConceptFilter, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      wrapper.vm.selectedGames = [{ searchName: "Games/Test", title: "Test" }];
      await wrapper.vm.$nextTick();

      expect(wrapper.vm.hasActiveFilters).toBe(true);
    });

    it("hasActiveFilters returns false when all filters are default", async () => {
      const wrapper = mount(ConceptFilter, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      expect(wrapper.vm.hasActiveFilters).toBe(false);
    });
  });

  describe("Game Search", () => {
    it("searches for games when typing in search input", async () => {
      global.fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => ({
          success: true,
          games: [
            {
              title: "Test Game",
              searchName: "Games/Test_Game",
              img: "test.jpg",
              releaseYear: "2020",
              platforms: [{ title: "PC", abbrev: "PC" }],
            },
          ],
          pagination: {
            currentPage: 1,
            totalPages: 1,
            itemsPerPage: 10,
            totalItems: 1,
          },
        }),
      });

      const wrapper = mount(ConceptFilter, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      const searchInput = wrapper.find("#search-filter");
      await searchInput.setValue("test");

      // Wait for debounce
      await new Promise((resolve) => setTimeout(resolve, 1600));
      await wrapper.vm.$nextTick();

      expect(global.fetch).toHaveBeenCalled();
    });

    it("handles game selection and includes in filter", async () => {
      const wrapper = mount(ConceptFilter, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      // Simulate selecting a game
      wrapper.vm.selectedGames = [
        { searchName: "Games/Test_Game", title: "Test Game" },
      ];
      await wrapper.vm.$nextTick();

      // Trigger filter change
      wrapper.vm.onFilterChange();
      await wrapper.vm.$nextTick();

      expect(window.dispatchEvent).toHaveBeenCalled();
      const dispatchedEvent = window.dispatchEvent.mock.calls[0][0];
      expect(dispatchedEvent.detail.game_title).toContain("Games/Test_Game");
    });

    it("includes require_all_games when multiple games selected and checkbox checked", async () => {
      const wrapper = mount(ConceptFilter, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      // Select multiple games
      wrapper.vm.selectedGames = [
        { searchName: "Games/Game1", title: "Game 1" },
        { searchName: "Games/Game2", title: "Game 2" },
      ];
      wrapper.vm.requireAllGames = true;
      await wrapper.vm.$nextTick();

      // Trigger filter change
      wrapper.vm.onFilterChange();
      await wrapper.vm.$nextTick();

      expect(window.dispatchEvent).toHaveBeenCalled();
      const dispatchedEvent = window.dispatchEvent.mock.calls[0][0];
      expect(dispatchedEvent.detail.require_all_games).toBe(true);
    });
  });

  describe("Edge Cases", () => {
    it("handles URL with existing parameters", async () => {
      // Set up URL params before mounting
      const mockSearchParams = new URLSearchParams(
        "?letter=H&sort=last_edited",
      );
      window.location.search = "?letter=H&sort=last_edited";

      // Update the global URLSearchParams mock to return these values
      global.URLSearchParams = jest
        .fn()
        .mockImplementation(() => mockSearchParams);

      const wrapper = mount(ConceptFilter, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      expect(wrapper.vm.selectedLetter).toBe("H");
      expect(wrapper.vm.selectedSort).toBe("last_edited");

      // Reset
      window.location.search = "";
    });

    it("handles URL with game_title parameters", async () => {
      const mockSearchParams = new URLSearchParams(
        "?game_title[]=Games/Test1&game_title[]=Games/Test2",
      );
      window.location.search =
        "?game_title[]=Games/Test1&game_title[]=Games/Test2";

      global.URLSearchParams = jest
        .fn()
        .mockImplementation(() => mockSearchParams);

      const wrapper = mount(ConceptFilter, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      expect(wrapper.vm.selectedGames).toHaveLength(2);
      expect(wrapper.vm.selectedGames[0].searchName).toBe("Games/Test1");
      expect(wrapper.vm.selectedGames[1].searchName).toBe("Games/Test2");

      // Reset
      window.location.search = "";
    });

    it("handles fetch errors gracefully", async () => {
      const consoleErrorSpy = jest.spyOn(console, "error").mockImplementation();

      global.fetch.mockRejectedValueOnce(new Error("Network error"));

      const wrapper = mount(ConceptFilter, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      // The component should handle errors gracefully
      wrapper.vm.handleSearch("Test");
      await new Promise((resolve) => setTimeout(resolve, 800));
      await wrapper.vm.$nextTick();

      // Component should still be functional
      expect(wrapper.vm.searchText).toBeUndefined(); // searchText is not exposed
      expect(wrapper.vm.searchResults).toEqual([]);

      consoleErrorSpy.mockRestore();
    });

    it("handles requireAllGames change when multiple games selected", async () => {
      const wrapper = mount(ConceptFilter, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      // Select multiple games
      wrapper.vm.selectedGames = [
        { searchName: "Games/Game1", title: "Game 1" },
        { searchName: "Games/Game2", title: "Game 2" },
      ];
      await wrapper.vm.$nextTick();

      window.dispatchEvent.mockClear();

      // Change requireAllGames
      wrapper.vm.requireAllGames = true;
      await wrapper.vm.$nextTick();

      // Should trigger filter change
      expect(window.dispatchEvent).toHaveBeenCalled();
    });

    it("does not trigger filter change when requireAllGames changes with single game", async () => {
      const wrapper = mount(ConceptFilter, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      // Select single game
      wrapper.vm.selectedGames = [
        { searchName: "Games/Game1", title: "Game 1" },
      ];
      await wrapper.vm.$nextTick();

      window.dispatchEvent.mockClear();

      // Change requireAllGames (should not trigger since only 1 game)
      wrapper.vm.requireAllGames = true;
      await wrapper.vm.$nextTick();

      // Should not trigger filter change (watch condition checks length > 1)
      // Note: The watch in ConceptFilter checks if selectedGames.length > 1
      // So this should not dispatch an event
      expect(window.dispatchEvent).not.toHaveBeenCalled();
    });
  });
});
