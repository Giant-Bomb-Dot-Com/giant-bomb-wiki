const { mount } = require("@vue/test-utils");
const GameList = require("../GameList.vue");

describe("GameList", () => {
  let wrapper;
  const mockGames = [
    {
      title: "The Legend of Zelda: Breath of the Wild",
      url: "/wiki/The_Legend_of_Zelda:_Breath_of_the_Wild",
      img: "zelda.jpg",
      date: "2017-03-03",
      platforms: ["Nintendo Switch", "Wii U"],
    },
    {
      title: "Super Mario Odyssey",
      url: "/wiki/Super_Mario_Odyssey",
      img: "mario.jpg",
      date: "2017-10-27",
      platforms: ["Nintendo Switch"],
    },
    {
      title: "God of War",
      url: "/wiki/God_of_War_(2018)",
      img: "gow.jpg",
      date: "2018-04-20",
      platforms: ["PlayStation 4"],
    },
    {
      title: "Elden Ring",
      url: "/wiki/Elden_Ring",
      img: "elden.jpg",
      date: "2022-02-25",
      platforms: ["PlayStation 5", "Xbox Series X", "PC"],
    },
  ];

  beforeEach(() => {
    // Clear URL parameters and event listeners
    window.history.replaceState({}, "", window.location.pathname);
    document.body.innerHTML = "";
  });

  afterEach(() => {
    if (wrapper) {
      wrapper.unmount();
    }
  });

  describe("Initial Render", () => {
    it("renders all games when no filters applied", async () => {
      wrapper = mount(GameList, {
        props: {
          initialData: JSON.stringify(mockGames),
        },
      });

      await wrapper.vm.$nextTick();

      const gameCards = wrapper.findAll(".game-card");
      expect(gameCards).toHaveLength(4);
    });

    it("renders game cards with correct structure", async () => {
      wrapper = mount(GameList, {
        props: {
          initialData: JSON.stringify(mockGames),
        },
      });

      await wrapper.vm.$nextTick();

      const firstCard = wrapper.find(".game-card");
      expect(firstCard.find(".game-image").exists()).toBe(true);
      expect(firstCard.find(".game-info").exists()).toBe(true);
      expect(firstCard.find(".game-title").exists()).toBe(true);
      expect(firstCard.find(".game-date").exists()).toBe(true);
      expect(firstCard.find(".item-platforms").exists()).toBe(true);
    });

    it("renders game titles correctly (sorted A-Z by default)", async () => {
      wrapper = mount(GameList, {
        props: {
          initialData: JSON.stringify(mockGames),
        },
      });

      await wrapper.vm.$nextTick();

      const titles = wrapper.findAll(".game-title");
      // Default sort is title-asc (A-Z)
      expect(titles[0].text()).toBe("Elden Ring");
      expect(titles[1].text()).toBe("God of War");
    });

    it("renders game images", async () => {
      wrapper = mount(GameList, {
        props: {
          initialData: JSON.stringify(mockGames),
        },
      });

      await wrapper.vm.$nextTick();

      const images = wrapper.findAll(".game-image img");
      expect(images.length).toBeGreaterThan(0);
      expect(images[0].attributes("alt")).toBeTruthy();
    });

    it("renders placeholder for missing images", async () => {
      const gamesWithoutImage = [
        {
          title: "Test Game",
          url: "/wiki/Test",
          img: "",
          date: "2023-01-01",
          platforms: ["PC"],
        },
      ];

      wrapper = mount(GameList, {
        props: {
          initialData: JSON.stringify(gamesWithoutImage),
        },
      });

      await wrapper.vm.$nextTick();

      expect(wrapper.find(".game-image-placeholder").exists()).toBe(true);
      expect(wrapper.find(".game-image img").exists()).toBe(false);
    });

    it("renders platform badges", async () => {
      wrapper = mount(GameList, {
        props: {
          initialData: JSON.stringify(mockGames),
        },
      });

      await wrapper.vm.$nextTick();

      const badges = wrapper.findAll(".platform-badge");
      expect(badges.length).toBeGreaterThan(0);
    });

    it("renders game links correctly", async () => {
      wrapper = mount(GameList, {
        props: {
          initialData: JSON.stringify(mockGames),
        },
      });

      await wrapper.vm.$nextTick();

      const firstLink = wrapper.find(".game-card-link");
      expect(firstLink.attributes("href")).toBeTruthy();
    });
  });

  describe("Search Filtering", () => {
    it("filters games by search query (case-insensitive)", async () => {
      wrapper = mount(GameList, {
        props: {
          initialData: JSON.stringify(mockGames),
        },
      });

      await wrapper.vm.$nextTick();

      // Trigger filter event
      const filterEvent = new CustomEvent("games-filter-changed", {
        detail: { search: "zelda", platform: "", sort: "title-asc" },
      });
      window.dispatchEvent(filterEvent);

      await wrapper.vm.$nextTick();

      const gameCards = wrapper.findAll(".game-card");
      expect(gameCards).toHaveLength(1);
      expect(gameCards[0].find(".game-title").text()).toBe(
        "The Legend of Zelda: Breath of the Wild",
      );
    });

    it("shows no results message when search yields no matches", async () => {
      wrapper = mount(GameList, {
        props: {
          initialData: JSON.stringify(mockGames),
        },
      });

      await wrapper.vm.$nextTick();

      const filterEvent = new CustomEvent("games-filter-changed", {
        detail: { search: "nonexistent", platform: "", sort: "title-asc" },
      });
      window.dispatchEvent(filterEvent);

      await wrapper.vm.$nextTick();

      expect(wrapper.find(".empty-state").exists()).toBe(true);
      expect(wrapper.find(".empty-state").text()).toContain("No games found");
    });
  });

  describe("Platform Filtering", () => {
    it("filters games by platform", async () => {
      wrapper = mount(GameList, {
        props: {
          initialData: JSON.stringify(mockGames),
        },
      });

      await wrapper.vm.$nextTick();

      const filterEvent = new CustomEvent("games-filter-changed", {
        detail: { search: "", platform: "PlayStation 4", sort: "title-asc" },
      });
      window.dispatchEvent(filterEvent);

      await wrapper.vm.$nextTick();

      const gameCards = wrapper.findAll(".game-card");
      expect(gameCards).toHaveLength(1);
      expect(gameCards[0].find(".game-title").text()).toBe("God of War");
    });

    it("shows games with multiple platforms when one matches", async () => {
      wrapper = mount(GameList, {
        props: {
          initialData: JSON.stringify(mockGames),
        },
      });

      await wrapper.vm.$nextTick();

      const filterEvent = new CustomEvent("games-filter-changed", {
        detail: { search: "", platform: "Nintendo Switch", sort: "title-asc" },
      });
      window.dispatchEvent(filterEvent);

      await wrapper.vm.$nextTick();

      const gameCards = wrapper.findAll(".game-card");
      expect(gameCards).toHaveLength(2); // Zelda and Mario
    });

    it("handles platform filter with partial matches", async () => {
      wrapper = mount(GameList, {
        props: {
          initialData: JSON.stringify(mockGames),
        },
      });

      await wrapper.vm.$nextTick();

      const filterEvent = new CustomEvent("games-filter-changed", {
        detail: { search: "", platform: "PlayStation", sort: "title-asc" },
      });
      window.dispatchEvent(filterEvent);

      await wrapper.vm.$nextTick();

      const gameCards = wrapper.findAll(".game-card");
      expect(gameCards).toHaveLength(2); // God of War (PS4) and Elden Ring (PS5)
    });
  });

  describe("Combined Filtering", () => {
    it("filters by both search and platform", async () => {
      wrapper = mount(GameList, {
        props: {
          initialData: JSON.stringify(mockGames),
        },
      });

      await wrapper.vm.$nextTick();

      const filterEvent = new CustomEvent("games-filter-changed", {
        detail: {
          search: "zelda",
          platform: "Nintendo Switch",
          sort: "title-asc",
        },
      });
      window.dispatchEvent(filterEvent);

      await wrapper.vm.$nextTick();

      const gameCards = wrapper.findAll(".game-card");
      expect(gameCards).toHaveLength(1);
      expect(gameCards[0].find(".game-title").text()).toBe(
        "The Legend of Zelda: Breath of the Wild",
      );
    });

    it("returns no results when filters do not match", async () => {
      wrapper = mount(GameList, {
        props: {
          initialData: JSON.stringify(mockGames),
        },
      });

      await wrapper.vm.$nextTick();

      const filterEvent = new CustomEvent("games-filter-changed", {
        detail: {
          search: "zelda",
          platform: "PlayStation 4",
          sort: "title-asc",
        },
      });
      window.dispatchEvent(filterEvent);

      await wrapper.vm.$nextTick();

      expect(wrapper.find(".empty-state").exists()).toBe(true);
    });
  });

  describe("Sorting", () => {
    it("sorts by title ascending (A-Z)", async () => {
      wrapper = mount(GameList, {
        props: {
          initialData: JSON.stringify(mockGames),
        },
      });

      await wrapper.vm.$nextTick();

      const filterEvent = new CustomEvent("games-filter-changed", {
        detail: { search: "", platform: "", sort: "title-asc" },
      });
      window.dispatchEvent(filterEvent);

      await wrapper.vm.$nextTick();

      const titles = wrapper.findAll(".game-title");
      expect(titles[0].text()).toBe("Elden Ring");
      expect(titles[1].text()).toBe("God of War");
      expect(titles[2].text()).toBe("Super Mario Odyssey");
      expect(titles[3].text()).toBe("The Legend of Zelda: Breath of the Wild");
    });

    it("sorts by title descending (Z-A)", async () => {
      wrapper = mount(GameList, {
        props: {
          initialData: JSON.stringify(mockGames),
        },
      });

      await wrapper.vm.$nextTick();

      const filterEvent = new CustomEvent("games-filter-changed", {
        detail: { search: "", platform: "", sort: "title-desc" },
      });
      window.dispatchEvent(filterEvent);

      await wrapper.vm.$nextTick();

      const titles = wrapper.findAll(".game-title");
      expect(titles[0].text()).toBe("The Legend of Zelda: Breath of the Wild");
      expect(titles[1].text()).toBe("Super Mario Odyssey");
      expect(titles[2].text()).toBe("God of War");
      expect(titles[3].text()).toBe("Elden Ring");
    });

    it("sorts by date descending (newest first)", async () => {
      wrapper = mount(GameList, {
        props: {
          initialData: JSON.stringify(mockGames),
        },
      });

      await wrapper.vm.$nextTick();

      const filterEvent = new CustomEvent("games-filter-changed", {
        detail: { search: "", platform: "", sort: "date-desc" },
      });
      window.dispatchEvent(filterEvent);

      await wrapper.vm.$nextTick();

      const titles = wrapper.findAll(".game-title");
      expect(titles[0].text()).toBe("Elden Ring"); // 2022
      expect(titles[1].text()).toBe("God of War"); // 2018
      expect(titles[2].text()).toBe("Super Mario Odyssey"); // 2017-10
      expect(titles[3].text()).toBe("The Legend of Zelda: Breath of the Wild"); // 2017-03
    });

    it("sorts by date ascending (oldest first)", async () => {
      wrapper = mount(GameList, {
        props: {
          initialData: JSON.stringify(mockGames),
        },
      });

      await wrapper.vm.$nextTick();

      const filterEvent = new CustomEvent("games-filter-changed", {
        detail: { search: "", platform: "", sort: "date-asc" },
      });
      window.dispatchEvent(filterEvent);

      await wrapper.vm.$nextTick();

      const titles = wrapper.findAll(".game-title");
      expect(titles[0].text()).toBe("The Legend of Zelda: Breath of the Wild"); // 2017-03
      expect(titles[1].text()).toBe("Super Mario Odyssey"); // 2017-10
      expect(titles[2].text()).toBe("God of War"); // 2018
      expect(titles[3].text()).toBe("Elden Ring"); // 2022
    });
  });

  describe("Edge Cases", () => {
    it("handles empty games array", async () => {
      wrapper = mount(GameList, {
        props: {
          initialData: JSON.stringify([]),
        },
      });

      await wrapper.vm.$nextTick();

      expect(wrapper.find(".empty-state").exists()).toBe(true);
      expect(wrapper.findAll(".game-card")).toHaveLength(0);
    });

    it("handles invalid JSON in initialData", async () => {
      // Suppress console.error for this test
      const consoleSpy = jest
        .spyOn(console, "error")
        .mockImplementation(() => {});

      wrapper = mount(GameList, {
        props: {
          initialData: "invalid-json",
        },
      });

      await wrapper.vm.$nextTick();

      // Should render without crashing
      expect(wrapper.find(".games-main").exists()).toBe(true);
      expect(wrapper.findAll(".game-card")).toHaveLength(0);

      consoleSpy.mockRestore();
    });

    it("handles missing initialData prop", async () => {
      // Suppress console.error for this test
      const consoleSpy = jest
        .spyOn(console, "error")
        .mockImplementation(() => {});

      wrapper = mount(GameList, {
        props: {
          initialData: "",
        },
      });

      await wrapper.vm.$nextTick();

      // Should render without crashing
      expect(wrapper.find(".games-main").exists()).toBe(true);

      consoleSpy.mockRestore();
    });

    it("handles games without platforms array", async () => {
      const gamesWithoutPlatforms = [
        {
          title: "Test Game",
          url: "/wiki/Test",
          img: "",
          date: "2023-01-01",
          platforms: [],
        },
      ];

      wrapper = mount(GameList, {
        props: {
          initialData: JSON.stringify(gamesWithoutPlatforms),
        },
      });

      await wrapper.vm.$nextTick();

      expect(wrapper.findAll(".platform-badge")).toHaveLength(0);
    });

    it("handles games with missing date field", async () => {
      const gamesWithoutDate = [
        {
          title: "Test Game",
          url: "/wiki/Test",
          img: "",
          date: "",
          platforms: ["PC"],
        },
      ];

      wrapper = mount(GameList, {
        props: {
          initialData: JSON.stringify(gamesWithoutDate),
        },
      });

      await wrapper.vm.$nextTick();

      // Should still render
      expect(wrapper.findAll(".game-card")).toHaveLength(1);
    });

    it("handles sorting with missing dates", async () => {
      const gamesWithMixedDates = [
        {
          title: "Game A",
          url: "/wiki/A",
          img: "",
          date: "2023-01-01",
          platforms: ["PC"],
        },
        {
          title: "Game B",
          url: "/wiki/B",
          img: "",
          date: "",
          platforms: ["PC"],
        },
      ];

      wrapper = mount(GameList, {
        props: {
          initialData: JSON.stringify(gamesWithMixedDates),
        },
      });

      await wrapper.vm.$nextTick();

      const filterEvent = new CustomEvent("games-filter-changed", {
        detail: { search: "", platform: "", sort: "date-desc" },
      });
      window.dispatchEvent(filterEvent);

      await wrapper.vm.$nextTick();

      // Should not crash
      expect(wrapper.findAll(".game-card")).toHaveLength(2);
    });
  });
});
