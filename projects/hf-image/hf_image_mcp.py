from pathlib import Path
from datetime import datetime
from huggingface_hub import InferenceClient
from mcp.server.fastmcp import FastMCP

mcp = FastMCP("Hugging Face Image Generator")

OUTPUT_DIR = Path("/root/hf-images")
OUTPUT_DIR.mkdir(parents=True, exist_ok=True)

client = InferenceClient()

@mcp.tool()
def generate_image(prompt: str) -> str:
    """Generate an image from a text description using Hugging Face."""
    
    image = client.text_to_image(
        prompt,
        model="black-forest-labs/FLUX.1-schnell"
    )

    filename = datetime.now().strftime("image_%Y%m%d_%H%M%S.png")
    output = OUTPUT_DIR / filename
    image.save(output)

    return f"Image generated successfully: {output}"

if __name__ == "__main__":
    mcp.run(transport="stdio")
